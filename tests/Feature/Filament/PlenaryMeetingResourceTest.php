<?php

use App\Enums\PlenaryMeetingStatus;
use App\Enums\RollCallImportStatus;
use App\Enums\VoteOption;
use App\Filament\Resources\PlenaryMeetings\Pages\EditPlenaryMeeting;
use App\Filament\Resources\PlenaryMeetings\Pages\ListPlenaryMeetings;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\PlenaryMeetings\RelationManagers\MotionsRelationManager;
use App\Jobs\ProcessRollCallImport;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\CouncilSession;
use App\Models\Deputy;
use App\Models\Membership;
use App\Models\Motion;
use App\Models\PlenaryMeeting;
use App\Models\RollCallImport;
use App\Models\User;
use App\Models\Vote;
use App\Models\VoteEvent;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('lists plenary meetings with question counts and actionable statuses', function () {
    $organization = CouncilOrganization::factory()->create(['name' => 'Зборівська міська рада']);
    $convocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create(['name' => '8 скликання']);
    $olderSession = CouncilSession::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => '98 сесія']);
    $newerSession = CouncilSession::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => '99 сесія']);
    $olderMeeting = PlenaryMeeting::factory()
        ->for($olderSession, 'councilSession')
        ->create(['date' => '2025-05-20']);
    $newerMeeting = PlenaryMeeting::factory()
        ->for($newerSession, 'councilSession')
        ->create(['date' => '2025-06-12']);
    $olderMotion = Motion::factory()->for($olderMeeting, 'plenaryMeeting')->create();
    VoteEvent::factory()->for($olderMotion)->count(2)->create();
    $newerMotions = Motion::factory()->for($newerMeeting, 'plenaryMeeting')->count(2)->create();
    VoteEvent::factory()->for($newerMotions[0])->create();
    VoteEvent::factory()->for($newerMotions[1])->count(2)->create();
    RollCallImport::factory()->create([
        'plenary_meeting_id' => $olderMeeting->id,
        'status' => RollCallImportStatus::AwaitingReview,
    ]);

    $page = livewire(ListPlenaryMeetings::class)
        ->assertCanSeeTableRecords([$newerMeeting, $olderMeeting], inOrder: true)
        ->assertSee('Питань')
        ->assertSee('Статус')
        ->assertSee('Є питання')
        ->assertSee('Потрібна перевірка')
        ->assertDontSee('Поіменних голосувань')
        ->assertDontSee('Орган місцевого самоврядування')
        ->assertTableColumnStateSet('motions_count', 2, $newerMeeting)
        ->assertTableColumnStateSet('status', PlenaryMeetingStatus::QuestionsAdded, $newerMeeting)
        ->assertTableColumnStateSet('motions_count', 1, $olderMeeting)
        ->assertTableColumnStateSet('status', PlenaryMeetingStatus::NeedsReview, $olderMeeting);

    expect(parse_url(PlenaryMeetingResource::getUrl(), PHP_URL_PATH))->toBe('/panel/meetings')
        ->and($page->instance()->getBreadcrumbs())->toBe([]);
});

it('creates a meeting without exposing the organization field', function () {
    $organization = CouncilOrganization::factory()->create();
    CouncilOrganization::factory()->create();
    $convocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create(['name' => '8 скликання']);
    $session = CouncilSession::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => '99 сесія']);

    livewire(ListPlenaryMeetings::class)
        ->callAction(TestAction::make('create'), [
            'council_session_id' => $session->id,
            'date' => '2025-06-12',
        ])
        ->assertHasNoFormErrors();

    $meeting = PlenaryMeeting::query()->firstOrFail();

    $this->assertModelExists($meeting);

    expect($meeting->councilSession->convocation->organization->is($organization))->toBeTrue()
        ->and($meeting->council_session_id)->toBe($session->id)
        ->and($meeting->date->toDateString())->toBe('2025-06-12');
});

it('creates a new council session from the session dropdown', function () {
    $organization = CouncilOrganization::factory()->create();
    $convocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create(['name' => '9 скликання']);

    livewire(ListPlenaryMeetings::class)
        ->mountAction('create')
        ->callFormComponentAction('council_session_id', 'createOption', [
            'name' => '101 сесія',
            'convocation_id' => $convocation->id,
        ])
        ->assertHasNoFormErrors();

    $session = CouncilSession::query()->where('name', '101 сесія')->firstOrFail();

    expect($session->convocation_id)->toBe($convocation->id);
});

it('creates a meeting and queues its uploaded roll-call files', function () {
    Storage::fake('local');
    Queue::fake([ProcessRollCallImport::class]);

    $convocation = Convocation::factory()->create();
    $session = CouncilSession::factory()->for($convocation, 'convocation')->create();

    livewire(ListPlenaryMeetings::class)
        ->callAction(TestAction::make('create'), [
            'council_session_id' => $session->id,
            'date' => '2025-06-12',
            'files' => [
                UploadedFile::fake()->create('roll-calls.pdf', 100, 'application/pdf'),
            ],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    $meeting = PlenaryMeeting::query()->firstOrFail();
    $import = RollCallImport::query()->firstOrFail();

    expect($import->plenary_meeting_id)->toBe($meeting->id)
        ->and($import->plenaryMeeting?->councilSession->is($session))->toBeTrue()
        ->and($import->user_id)->toBe(auth()->id())
        ->and($import->status)->toBe(RollCallImportStatus::Queued);

    Storage::disk('local')->assertExists($import->file_path);
    Queue::assertPushed(ProcessRollCallImport::class, fn (ProcessRollCallImport $job): bool => $job->rollCallImport->is($import));
});

it('queues additional roll-call files from the meeting workspace', function () {
    Storage::fake('local');
    Queue::fake([ProcessRollCallImport::class]);

    $meeting = PlenaryMeeting::factory()->create();

    livewire(EditPlenaryMeeting::class, ['record' => $meeting->id])
        ->callAction(TestAction::make('uploadRollCallFiles'), [
            'files' => [UploadedFile::fake()->create('additional-roll-calls.pdf', 100, 'application/pdf')],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();

    $import = RollCallImport::query()->firstOrFail();

    expect($import->plenary_meeting_id)->toBe($meeting->id)
        ->and($import->plenaryMeeting?->councilSession->is($meeting->councilSession))->toBeTrue();

    Storage::disk('local')->assertExists($import->file_path);
    Queue::assertPushed(ProcessRollCallImport::class, fn (ProcessRollCallImport $job): bool => $job->rollCallImport->is($import));
});

it('creates a question with individual votes and displays calculated vote counts', function () {
    $meeting = PlenaryMeeting::factory()->create();
    $yesVoter = Deputy::factory()->create([
        'family_name' => 'Іваненко',
        'first_name' => 'Іван',
        'additional_name' => 'Іванович',
    ]);
    $noVoter = Deputy::factory()->create([
        'family_name' => 'Петренко',
        'first_name' => 'Петро',
        'additional_name' => 'Петрович',
    ]);
    Membership::factory()
        ->for($yesVoter, 'deputy')
        ->for($meeting->councilSession->convocation->organization, 'organization')
        ->for($meeting->councilSession->convocation, 'convocation')
        ->create(['voting_identifier' => 'COUNCIL-001']);
    Membership::factory()
        ->for($noVoter, 'deputy')
        ->for($meeting->councilSession->convocation->organization, 'organization')
        ->for($meeting->councilSession->convocation, 'convocation')
        ->create(['voting_identifier' => 'COUNCIL-002']);

    livewire(MotionsRelationManager::class, [
        'ownerRecord' => $meeting,
        'pageClass' => EditPlenaryMeeting::class,
    ])
        ->callTableAction('create', null, [
            'number' => 7,
            'project_number' => '14/26',
            'title' => 'Про затвердження програми',
            'result' => 'passed',
            'text' => 'decision-14-26.pdf',
            'voteEvents' => [[
                'votes' => [
                    ['deputy_id' => $yesVoter->id, 'option' => VoteOption::Yes->value],
                    ['deputy_id' => $noVoter->id, 'option' => VoteOption::No->value],
                ],
            ]],
        ])
        ->assertHasNoFormErrors();

    $motion = Motion::query()->where('plenary_meeting_id', $meeting->id)->firstOrFail();
    $votes = Vote::query()->whereHas('voteEvent', fn ($query) => $query->where('motion_id', $motion->id))
        ->orderBy('id')
        ->get();

    expect($motion->uid)->toBe("{$meeting->date->toDateString()}-{$meeting->id}-7")
        ->and($motion->number)->toBe(7)
        ->and($votes)->toHaveCount(2)
        ->and($votes[0]->voter_identifier)->toBe('COUNCIL-001')
        ->and($votes[0]->voter_name)->toBe($yesVoter->full_name)
        ->and($votes[1]->option)->toBe(VoteOption::No);

    livewire(MotionsRelationManager::class, [
        'ownerRecord' => $meeting,
        'pageClass' => EditPlenaryMeeting::class,
    ])
        ->assertTableColumnStateSet('votes_yes', 1, $motion)
        ->assertTableColumnStateSet('votes_no', 1, $motion);
});
