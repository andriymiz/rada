<?php

use App\Enums\RollCallImportStatus;
use App\Enums\VoteOption;
use App\Filament\Resources\PlenaryMeetings\Pages\EditPlenaryMeeting;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\PlenaryMeetings\RelationManagers\RollCallImportsRelationManager;
use App\Filament\Resources\RollCallImports\Pages\EditRollCallImport;
use App\Filament\Resources\RollCallImports\Pages\ViewRollCallImport;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Jobs\ProcessRollCallImport;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\CouncilSession;
use App\Models\Deputy;
use App\Models\Motion;
use App\Models\Person;
use App\Models\PlenaryMeeting;
use App\Models\RollCallImport;
use App\Models\User;
use App\Notifications\RollCallImportProcessed;
use App\Services\RollCallPdfParser;
use Carbon\CarbonInterval;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('shows imports only within their meeting and has no global import list', function () {
    $meeting = PlenaryMeeting::factory()->create();
    $otherMeeting = PlenaryMeeting::factory()->create();
    $reviewableImport = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::AwaitingReview,
        'parsed_result' => [
            'session' => null,
            'motions' => [[
                'question_number' => 1,
                'title' => 'Питання на підтвердження',
                'result' => 'Прийнято',
                'project_number' => null,
                'counts' => [],
                'votes' => [],
            ]],
        ],
    ]);
    $completedImport = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::Completed,
    ]);
    $failedImport = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::Failed,
        'error_message' => 'Не вдалося розібрати PDF.',
    ]);
    $otherImport = RollCallImport::factory()->create([
        'plenary_meeting_id' => $otherMeeting->id,
    ]);

    $table = livewire(RollCallImportsRelationManager::class, [
        'ownerRecord' => $meeting,
        'pageClass' => EditPlenaryMeeting::class,
    ])
        ->assertCanSeeTableRecords([$reviewableImport, $completedImport, $failedImport])
        ->assertCanNotSeeTableRecords([$otherImport])
        ->assertTableActionVisible('review', $reviewableImport)
        ->assertTableActionHasUrl('review', RollCallImportResource::getUrl('edit', ['record' => $reviewableImport]), $reviewableImport)
        ->assertTableActionVisible('view', $completedImport)
        ->assertTableActionHidden('review', $completedImport)
        ->assertTableActionVisible('viewError', $failedImport);

    expect(array_keys(RollCallImportResource::getPages()))->toBe(['view', 'edit'])
        ->and(RollCallImportResource::shouldRegisterNavigation())->toBeFalse()
        ->and($table->instance()->getTable()->getPollingInterval())->toBeNull();

    $this->get('/panel/meetings/imports')->assertNotFound();

    $edit = livewire(EditRollCallImport::class, ['record' => $reviewableImport->id])
        ->assertSee('Підтвердити');

    expect(array_values($edit->instance()->getBreadcrumbs()))
        ->toBe(['Засідання', 'Імпорти']);

    $view = livewire(ViewRollCallImport::class, ['record' => $completedImport->id])
        ->assertOk()
        ->assertSee('example.pdf');

    expect(array_values($view->instance()->getBreadcrumbs()))
        ->toBe(['Засідання', 'Імпорти']);
});

it('shows the error modal action only for imports with a processing error', function () {
    $meeting = PlenaryMeeting::factory()->create();
    $failedImport = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::Failed,
        'error_message' => 'Не вдалося розібрати PDF.',
    ]);
    $queuedImport = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::Queued,
    ]);

    livewire(RollCallImportsRelationManager::class, [
        'ownerRecord' => $meeting,
        'pageClass' => EditPlenaryMeeting::class,
    ])
        ->assertTableActionVisible('viewError', $failedImport)
        ->assertTableActionHidden('viewError', $queuedImport)
        ->mountTableAction('viewError', $failedImport)
        ->assertActionMounted(TestAction::make('viewError')->table($failedImport))
        ->assertMountedActionModalSee(['Помилка імпорту', 'Не вдалося розібрати PDF.']);
});

it('requeues a failed import from the error modal', function () {
    $meeting = PlenaryMeeting::factory()->create();
    $failedImport = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::Failed,
        'error_message' => 'Не вдалося розібрати PDF.',
        'parsed_result' => ['stale' => true],
        'processed_at' => now(),
    ]);

    Queue::fake([ProcessRollCallImport::class]);

    $page = livewire(RollCallImportsRelationManager::class, [
        'ownerRecord' => $meeting,
        'pageClass' => EditPlenaryMeeting::class,
    ])
        ->mountTableAction('viewError', $failedImport)
        ->assertMountedActionModalSee([
            'Помилка імпорту',
            'Не вдалося розібрати PDF.',
            'Спробувати ще раз',
        ]);

    $page->callMountedAction()
        ->assertNotified();

    expect($failedImport->fresh()->status)->toBe(RollCallImportStatus::Queued)
        ->and($failedImport->fresh()->error_message)->toBeNull()
        ->and($failedImport->fresh()->parsed_result)->toBeNull()
        ->and($failedImport->fresh()->processed_at)->toBeNull();

    Queue::assertPushed(ProcessRollCallImport::class, fn (ProcessRollCallImport $job): bool => $job->rollCallImport->is($failedImport));
});

it('derives the session label from the linked meeting', function () {
    $convocation = Convocation::factory()->create(['name' => '8 скликання']);
    $session = CouncilSession::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => 'Сесія № 2']);
    $meeting = PlenaryMeeting::factory()
        ->for($session, 'councilSession')
        ->create();
    $import = RollCallImport::factory()
        ->for($meeting, 'plenaryMeeting')
        ->create();

    expect(Schema::hasColumn('roll_call_imports', 'council_session_id'))->toBeFalse();
    expect($import->sessionLabel())->toBe('Сесія № 2 (8 скликання)');
});

it('uses the parsed session label when no meeting is linked', function () {
    $import = RollCallImport::factory()->create([
        'plenary_meeting_id' => null,
        'parsed_result' => ['session' => '42 сесія 8 скликання'],
    ]);

    expect($import->sessionLabel())->toBe('42 сесія (8 скликання)');
});

it('limits the pending processing scope to queued and processing imports', function () {
    $queued = RollCallImport::factory()->create(['status' => RollCallImportStatus::Queued]);
    $processing = RollCallImport::factory()->create(['status' => RollCallImportStatus::Processing]);
    RollCallImport::factory()->create(['status' => RollCallImportStatus::AwaitingReview]);
    RollCallImport::factory()->create(['status' => RollCallImportStatus::Completed]);
    RollCallImport::factory()->create(['status' => RollCallImportStatus::Failed]);

    expect(RollCallImport::query()->pendingOrProcessing()->pluck('id')->all())
        ->toBe([$queued->id, $processing->id]);
});

it('shows parsed agenda items and votes while an import awaits confirmation', function () {
    $meeting = PlenaryMeeting::factory()->create();
    $import = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::AwaitingReview,
        'parsed_result' => [
            'session' => '99 сесія 8 скликання',
            'motions' => [[
                'question_number' => 4,
                'project_number' => '12/3',
                'title' => 'Про затвердження бюджету',
                'result' => 'Прийнято',
                'counts' => [
                    'for' => 7,
                    'against' => 2,
                    'abstain' => 1,
                    'not_voting' => 0,
                    'absent' => 3,
                ],
                'votes' => [
                    ['name' => 'Іваненко Іван Іванович', 'result' => 'За'],
                    ['name' => 'Петренко Петро Петрович', 'result' => 'Проти'],
                ],
            ]],
        ],
    ]);

    livewire(EditRollCallImport::class, ['record' => $import->id])
        ->assertOk()
        ->assertSee('Про затвердження бюджету')
        ->assertSee('12/3')
        ->assertSeeInOrder(['№ 12/3', 'Про затвердження бюджету'])
        ->assertSee('ПРИЙНЯТО')
        ->assertSee('Іваненко Іван Іванович')
        ->assertSee('Петренко Петро Петрович')
        ->assertSee('Підтвердити')
        ->assertDontSee('Відхилити')
        ->assertSee('Переглянути PDF');
});

it('does not complete an import before every question is confirmed', function () {
    $meeting = PlenaryMeeting::factory()->create();
    $import = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::AwaitingReview,
        'parsed_result' => [
            'motions' => [[
                'question_number' => 1,
                'title' => 'Питання до підтвердження',
                'result' => 'Прийнято',
                'votes' => [],
            ]],
        ],
    ]);

    livewire(EditRollCallImport::class, ['record' => $import->id])
        ->call('save')
        ->assertHasErrors(['data']);

    expect($import->fresh()->status)->toBe(RollCallImportStatus::AwaitingReview)
        ->and(Motion::query()->where('plenary_meeting_id', $meeting->id)->count())->toBe(0);
});

it('creates each motion as it is confirmed and completes only after all motions are confirmed', function () {
    $organization = CouncilOrganization::factory()->create();
    $convocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $session = CouncilSession::factory()->for($convocation, 'convocation')->create();
    $meeting = PlenaryMeeting::factory()
        ->for($session, 'councilSession')
        ->create(['date' => '2025-06-12']);
    $deputy = Deputy::factory()
        ->for(Person::factory()->state([
            'last_name' => 'Іваненко',
            'first_name' => 'Іван',
            'middle_name' => 'Іванович',
        ]), 'person')
        ->for($convocation, 'convocation')
        ->create();
    $deputy->update(['voting_identifier' => 'COUNCIL-001']);
    $import = RollCallImport::factory()->create([
        'plenary_meeting_id' => $meeting->id,
        'status' => RollCallImportStatus::AwaitingReview,
        'parsed_result' => [
            'motions' => [
                [
                    'question_number' => 1,
                    'title' => 'Перше питання',
                    'project_number' => '10/25',
                    'result' => 'Прийнято',
                    'counts' => [
                        'for' => 1,
                        'against' => 1,
                        'abstain' => 0,
                        'not_voting' => 0,
                        'absent' => 0,
                    ],
                    'votes' => [
                        ['name' => $deputy->full_name, 'result' => 'За'],
                        ['name' => 'Невідомий Депутат', 'result' => 'Проти'],
                    ],
                ],
                [
                    'question_number' => 2,
                    'title' => 'Друге питання',
                    'project_number' => null,
                    'result' => 'Не прийнято',
                    'counts' => [
                        'for' => 0,
                        'against' => 0,
                        'abstain' => 0,
                        'not_voting' => 0,
                        'absent' => 0,
                    ],
                    'votes' => [],
                ],
            ],
        ],
    ]);

    $page = livewire(EditRollCallImport::class, ['record' => $import->id])
        ->assertSee('Засідання');

    $page->call('reviewMotion', 0)
        ->assertNotified()
        ->assertHasNoFormErrors();

    expect($import->fresh()->status)->toBe(RollCallImportStatus::AwaitingReview);

    $motion = Motion::query()->where('plenary_meeting_id', $meeting->id)->firstOrFail();
    $votes = $motion->voteEvents()->firstOrFail()->votes()->orderBy('id')->get();

    expect($motion->number)->toBe(1)
        ->and($motion->title)->toBe('Перше питання')
        ->and($motion->project_number)->toBe('10/25')
        ->and($votes)->toHaveCount(2)
        ->and($votes[0]->deputy_id)->toBe($deputy->id)
        ->and($votes[0]->voter_identifier)->toBe('COUNCIL-001')
        ->and($votes[0]->option)->toBe(VoteOption::Yes)
        ->and($votes[1]->deputy_id)->toBeNull()
        ->and($votes[1]->voter_identifier)->toBeNull()
        ->and($votes[1]->voter_name)->toBe('Невідомий Депутат');

    expect(Schema::hasColumn('motions', 'roll_call_import_id'))->toBeFalse();

    $page->call('reviewMotion', 1)
        ->assertNotified();

    $page->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Імпорт завершено')
        ->assertRedirect(PlenaryMeetingResource::getUrl('edit', ['record' => $meeting]));

    expect($import->fresh()->status)->toBe(RollCallImportStatus::Completed)
        ->and($import->fresh()->plenary_meeting_id)->toBe($meeting->id)
        ->and($import->fresh()->plenaryMeeting?->councilSession->is($session))->toBeTrue()
        ->and($import->fresh()->parsed_result['motions'][0]['review_status'])->toBe('approved')
        ->and($import->fresh()->parsed_result['motions'][1]['review_status'])->toBe('approved')
        ->and(Motion::query()->where('plenary_meeting_id', $meeting->id)->count())->toBe(2);
});

it('rejects confirmation of an import that is not linked to a meeting', function () {
    $import = RollCallImport::factory()->create([
        'plenary_meeting_id' => null,
        'status' => RollCallImportStatus::AwaitingReview,
        'parsed_result' => [
            'motions' => [[
                'question_number' => 1,
                'title' => 'Питання',
                'result' => 'Прийнято',
                'votes' => [],
            ]],
        ],
    ]);

    livewire(EditRollCallImport::class, ['record' => $import->id])
        ->call('reviewMotion', 0)
        ->assertHasErrors(['data']);

    expect($import->fresh()->status)->toBe(RollCallImportStatus::AwaitingReview)
        ->and($import->fresh()->plenary_meeting_id)->toBeNull()
        ->and(Motion::query()->count())->toBe(0);
});

it('does not allow completed imports to be edited', function () {
    $import = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::Completed,
    ]);

    livewire(EditRollCallImport::class, ['record' => $import->id])
        ->assertForbidden();
});

it('soft deletes imports without deleting their uploaded PDFs', function () {
    $meeting = PlenaryMeeting::factory()->create();
    $import = RollCallImport::factory()->create(['plenary_meeting_id' => $meeting->id]);
    Storage::fake('local');
    Storage::disk('local')->put($import->file_path, '%PDF-1.4');

    livewire(RollCallImportsRelationManager::class, [
        'ownerRecord' => $meeting,
        'pageClass' => EditPlenaryMeeting::class,
    ])
        ->callAction(TestAction::make('delete')->table($import));

    $this->assertSoftDeleted($import);
    Storage::disk('local')->assertExists($import->file_path);
    $this->get(route('roll-call-imports.pdf', ['rollCallImport' => $import]))
        ->assertNotFound();
});

it('serves the uploaded PDF inline to authenticated users', function () {
    $import = RollCallImport::factory()->create();
    Storage::fake('local');
    Storage::disk('local')->put($import->file_path, '%PDF-1.4');

    $response = $this->get(route('roll-call-imports.pdf', ['rollCallImport' => $import]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
});

it('requires authentication to access an uploaded PDF', function () {
    $import = RollCallImport::factory()->create();
    auth()->logout();

    $this->get(route('roll-call-imports.pdf', ['rollCallImport' => $import]))
        ->assertRedirect(route('filament.rada.auth.login'));
});

it('returns not found when the uploaded PDF is missing', function () {
    $import = RollCallImport::factory()->create();
    Storage::fake('local');

    $this->get(route('roll-call-imports.pdf', ['rollCallImport' => $import]))
        ->assertNotFound();
});

it('marks the import as awaiting confirmation, persists parsed data, and notifies its uploader', function () {
    $import = RollCallImport::factory()->create();

    Sleep::fake();
    Storage::fake('local');
    Storage::disk('local')->put(
        $import->file_path,
        File::get(base_path('tests/Fixtures/roll-call-votes-99.pdf')),
    );
    Log::spy();

    (new ProcessRollCallImport($import))->handle(app(RollCallPdfParser::class));

    $import->refresh();
    $notification = $import->user->notifications()->firstOrFail();

    expect($import->status)->toBe(RollCallImportStatus::AwaitingReview)
        ->and($import->processed_at)->not->toBeNull()
        ->and($import->parsed_result['page_count'])->toBe(6)
        ->and(count($import->parsed_result['motions']))->toBe(6)
        ->and($notification->data['title'])->toBe('Імпорт потребує підтвердження')
        ->and($notification->data['body'])->toContain('підтвердіть усі питання')
        ->and($notification->data['format'])->toBe('filament');

    Log::shouldHaveReceived('info')
        ->once()
        ->with('Roll-call PDF parsed', Mockery::on(fn (array $context): bool => $context['roll_call_import_id'] === $import->id
            && $context['filename'] === $import->original_filename
            && $context['parsed_result']['page_count'] === 6
            && count($context['parsed_result']['motions']) === 6
            && $context['parsed_result']['motions'][5]['question_number'] === 6
            && count($context['parsed_result']['motions'][5]['votes']) === 16));

    Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalSeconds === 3.0);
});

it('marks a failed import and notifies its uploader', function () {
    $import = RollCallImport::factory()->create();

    Notification::fake();

    (new ProcessRollCallImport($import))->failed(new RuntimeException('PDF не містить сторінок.'));

    expect($import->fresh()->status)->toBe(RollCallImportStatus::Failed)
        ->and($import->fresh()->error_message)->toBe('PDF не містить сторінок.');

    Notification::assertSentTo($import->user, RollCallImportProcessed::class);
});
