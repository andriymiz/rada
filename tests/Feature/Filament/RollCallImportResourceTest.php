<?php

use App\Enums\RollCallImportStatus;
use App\Filament\Resources\RollCallImports\Pages\EditRollCallImport;
use App\Filament\Resources\RollCallImports\Pages\ListRollCallImports;
use App\Filament\Resources\RollCallImports\Pages\ViewRollCallImport;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Jobs\ProcessRollCallImport;
use App\Models\ParliamentaryConvocation;
use App\Models\ParliamentarySession;
use App\Models\RollCallImport;
use App\Models\User;
use App\Notifications\RollCallImportProcessed;
use App\Services\RollCallPdfParser;
use Carbon\CarbonInterval;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('uploads multiple PDFs from the import history and queues each file', function () {
    Storage::fake('local');
    Queue::fake([ProcessRollCallImport::class]);

    livewire(ListRollCallImports::class)
        ->callAction(TestAction::make('upload'), [
            'files' => [
                UploadedFile::fake()->create('roll-calls-1.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->create('roll-calls-2.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertSee('roll-calls-1.pdf')
        ->assertSee('roll-calls-2.pdf');

    $imports = RollCallImport::query()->orderBy('original_filename')->get();

    expect($imports)->toHaveCount(2);

    foreach ($imports as $import) {
        expect($import->session_id)->toBeNull()
            ->and($import->user_id)->toBe($this->user->id)
            ->and($import->status)->toBe(RollCallImportStatus::Queued);

        Storage::disk('local')->assertExists($import->file_path);
        Queue::assertPushed(ProcessRollCallImport::class, fn (ProcessRollCallImport $job): bool => $job->rollCallImport->is($import));
    }
});

it('rejects non-PDF files in the upload modal', function () {
    Storage::fake('local');
    Queue::fake([ProcessRollCallImport::class]);

    livewire(ListRollCallImports::class)
        ->callAction(TestAction::make('upload'), [
            'files' => [UploadedFile::fake()->create('roll-calls.txt', 10, 'text/plain')],
        ])
        ->assertHasFormErrors(['files']);

    expect(RollCallImport::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('shows the newest imports first and permits reviewing imports awaiting confirmation', function () {
    $convocation = ParliamentaryConvocation::factory()->create(['name' => '8 скликання']);
    $session = ParliamentarySession::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => '10 сесія']);
    $olderImport = RollCallImport::factory()->create([
        'session_id' => null,
        'parsed_result' => ['session' => '99 сесія 8 скликання'],
    ]);
    $reviewableImport = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::AwaitingReview,
        'session_id' => null,
        'parsed_result' => [
            'session' => null,
            'motions' => [[
                'question_number' => 1,
                'title' => 'Питання на підтвердження',
                'votes' => [],
            ]],
        ],
    ]);
    $persistedSessionImport = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::Completed,
        'session_id' => $session->id,
        'parsed_result' => ['session' => '99 сесія 7 скликання'],
    ]);
    $newestImport = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::Completed,
        'session_id' => null,
        'parsed_result' => ['session' => null],
    ]);

    $list = livewire(ListRollCallImports::class)
        ->assertCanSeeTableRecords([$newestImport, $persistedSessionImport, $reviewableImport, $olderImport], inOrder: true)
        ->assertTableActionVisible('edit', $reviewableImport)
        ->assertTableActionHidden('view', $reviewableImport)
        ->assertTableActionHasUrl('edit', RollCallImportResource::getUrl('edit', ['record' => $reviewableImport]), $reviewableImport)
        ->assertTableActionVisible('view', $newestImport)
        ->assertTableActionHidden('edit', $newestImport)
        ->assertTableActionHasUrl('view', RollCallImportResource::getUrl('view', ['record' => $newestImport]), $newestImport)
        ->assertSee('99 сесія (8 скликання)')
        ->assertSee('10 сесія (8 скликання)')
        ->assertSee('Очікує підтвердження')
        ->assertSee('-');

    expect($list->instance()->getTable()->getPollingInterval())->toBe('3s');

    livewire(ViewRollCallImport::class, ['record' => $olderImport->id])
        ->assertForbidden();

    livewire(EditRollCallImport::class, ['record' => $reviewableImport->id])
        ->assertSee('Підтвердити');

    livewire(ViewRollCallImport::class, ['record' => $newestImport->id])
        ->assertOk()
        ->assertSee('example.pdf');
});

it('shows the error modal action only for imports with a processing error', function () {
    $failedImport = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::Failed,
        'error_message' => 'Не вдалося розібрати PDF.',
    ]);
    $queuedImport = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::Queued,
    ]);

    livewire(ListRollCallImports::class)
        ->assertTableActionVisible('viewError', $failedImport)
        ->assertTableActionHidden('viewError', $queuedImport)
        ->mountTableAction('viewError', $failedImport)
        ->assertActionMounted(TestAction::make('viewError')->table($failedImport))
        ->assertMountedActionModalSee(['Помилка обробки імпорту', 'Не вдалося розібрати PDF.']);
});

it('requeues a failed import from the error modal', function () {
    $failedImport = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::Failed,
        'error_message' => 'Не вдалося розібрати PDF.',
        'parsed_result' => ['stale' => true],
        'processed_at' => now(),
    ]);

    Queue::fake([ProcessRollCallImport::class]);

    $page = livewire(ListRollCallImports::class)
        ->mountTableAction('viewError', $failedImport)
        ->assertMountedActionModalSee([
            'Помилка обробки імпорту',
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
    $import = RollCallImport::factory()->create([
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

it('completes an import only after every motion is confirmed', function () {
    $convocation = ParliamentaryConvocation::factory()->create();
    $session = ParliamentarySession::factory()->for($convocation, 'convocation')->create();
    $import = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::AwaitingReview,
        'parsed_result' => [
            'motions' => [
                [
                    'question_number' => 1,
                    'title' => 'Перше питання',
                    'votes' => [],
                ],
                [
                    'question_number' => 2,
                    'title' => 'Друге питання',
                    'votes' => [],
                ],
            ],
        ],
    ]);

    $page = livewire(EditRollCallImport::class, ['record' => $import->id])
        ->assertSee('Скликання')
        ->assertSee('Сесія')
        ->fillForm([
            'convocation_id' => $convocation->id,
            'session_id' => $session->id,
        ])
        ->call('save')
        ->assertHasFormErrors(['session_id']);

    expect($import->fresh()->status)->toBe(RollCallImportStatus::AwaitingReview);

    $page->call('reviewMotion', 0)
        ->assertNotified()
        ->call('reviewMotion', 1)
        ->assertNotified();

    expect($import->fresh()->status)->toBe(RollCallImportStatus::AwaitingReview);

    $page->fillForm([
        'convocation_id' => $convocation->id,
        'session_id' => $session->id,
    ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Імпорт збережено та завершено')
        ->assertRedirect();

    expect($import->fresh()->status)->toBe(RollCallImportStatus::Completed)
        ->and($import->fresh()->session_id)->toBe($session->id)
        ->and($import->fresh()->parsed_result['motions'][0]['review_status'])->toBe('approved')
        ->and($import->fresh()->parsed_result['motions'][1]['review_status'])->toBe('approved');
});

it('rejects a session that does not belong to the selected convocation', function () {
    $selectedConvocation = ParliamentaryConvocation::factory()->create();
    $otherConvocation = ParliamentaryConvocation::factory()->create();
    $otherSession = ParliamentarySession::factory()->for($otherConvocation, 'convocation')->create();
    $import = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::AwaitingReview,
        'session_id' => null,
        'parsed_result' => [
            'motions' => [[
                'question_number' => 1,
                'title' => 'Питання',
                'review_status' => 'approved',
                'votes' => [],
            ]],
        ],
    ]);

    livewire(EditRollCallImport::class, ['record' => $import->id])
        ->fillForm([
            'convocation_id' => $selectedConvocation->id,
            'session_id' => $otherSession->id,
        ])
        ->call('save')
        ->assertHasFormErrors(['session_id']);

    expect($import->fresh()->status)->toBe(RollCallImportStatus::AwaitingReview)
        ->and($import->fresh()->session_id)->toBeNull();
});

it('does not allow completed imports to be edited', function () {
    $import = RollCallImport::factory()->create([
        'status' => RollCallImportStatus::Completed,
    ]);

    livewire(EditRollCallImport::class, ['record' => $import->id])
        ->assertForbidden();
});

it('soft deletes imports without deleting their uploaded PDFs', function () {
    $import = RollCallImport::factory()->create();
    Storage::fake('local');
    Storage::disk('local')->put($import->file_path, '%PDF-1.4');

    livewire(ListRollCallImports::class)
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
