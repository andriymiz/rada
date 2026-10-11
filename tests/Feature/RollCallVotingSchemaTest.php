<?php

use App\Enums\MotionResult;
use App\Enums\VoteOption;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\CouncilSession;
use App\Models\Deputy;
use App\Models\Motion;
use App\Models\Person;
use App\Models\PlenaryMeeting;
use App\Models\Vote;
use App\Models\VoteEvent;
use Database\Seeders\RollCallVotingDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('stores an agenda item without inventing a vote event', function () {
    $organization = CouncilOrganization::factory()->create();
    $convocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $session = CouncilSession::factory()->for($convocation, 'convocation')->create();
    $meeting = PlenaryMeeting::factory()
        ->for($session, 'councilSession')
        ->create(['date' => '2025-06-12']);
    $motion = Motion::factory()
        ->for($meeting, 'plenaryMeeting')
        ->create([
            'uid' => '2025-06-12-1',
            'number' => 1,
            'result' => MotionResult::NotConsidered,
        ]);

    expect($motion->plenaryMeeting->councilSession->convocation->organization->edrpou)->toBe($organization->edrpou)
        ->and($motion->plenaryMeeting->councilSession->convocation->is($convocation))->toBeTrue()
        ->and($motion->voteEvents)->toBeEmpty()
        ->and($motion->result)->toBe(MotionResult::NotConsidered);
});

it('scopes convocation names to their council organization', function () {
    $firstOrganization = CouncilOrganization::factory()->create();
    $secondOrganization = CouncilOrganization::factory()->create();

    $firstConvocation = Convocation::factory()
        ->for($firstOrganization, 'organization')
        ->create(['name' => '8 скликання']);
    $secondConvocation = Convocation::factory()
        ->for($secondOrganization, 'organization')
        ->create(['name' => '8 скликання']);

    expect($firstConvocation->organization->is($firstOrganization))->toBeTrue()
        ->and($secondConvocation->organization->is($secondOrganization))->toBeTrue();
});

it('allows a motion to have multiple distinct vote events', function () {
    $motion = Motion::factory()->create();

    $firstEvent = VoteEvent::factory()
        ->for($motion, 'motion')
        ->create(['identifier' => 'vote-1']);
    $secondEvent = VoteEvent::factory()
        ->for($motion, 'motion')
        ->create(['identifier' => 'vote-2']);
    $deputy = Deputy::factory()->create();

    Vote::factory()->for($firstEvent, 'voteEvent')->forDeputy($deputy)->create();
    Vote::factory()->for($secondEvent, 'voteEvent')->forDeputy($deputy)->create();

    expect($motion->voteEvents()->pluck('identifier')->all())
        ->toBe(['vote-1', 'vote-2'])
        ->and($firstEvent->motion->is($motion))->toBeTrue()
        ->and($secondEvent->motion->is($motion))->toBeTrue()
        ->and($deputy->votes()->count())->toBe(2)
        ->and(Schema::hasColumn('vote_events', 'start_date'))->toBeFalse()
        ->and(Schema::hasColumn('vote_events', 'end_date'))->toBeFalse();
});

it('stores one immutable voter snapshot per person for a recorded event', function () {
    $motion = Motion::factory()->create(['uid' => '2025-06-12-2']);
    $event = VoteEvent::factory()->for($motion, 'motion')->create();
    $convocation = $motion->plenaryMeeting->councilSession->convocation;
    $deputy = Deputy::factory()
        ->for(Person::factory()->state([
            'last_name' => 'Іваненко',
            'first_name' => 'Олександр',
            'middle_name' => 'Петрович',
        ]), 'person')
        ->for($convocation, 'convocation')
        ->create();
    $deputy->update(['voting_identifier' => 'COUNCIL-001']);

    $vote = Vote::factory()
        ->for($event, 'voteEvent')
        ->forDeputy($deputy)
        ->create(['option' => VoteOption::Yes]);

    expect($event->votes)->toHaveCount(1)
        ->and($vote->deputy->is($deputy))->toBeTrue()
        ->and($vote->voter_identifier)->toBe('COUNCIL-001')
        ->and($vote->voter_name)->toBe('Іваненко Олександр Петрович')
        ->and($vote->option)->toBe(VoteOption::Yes);

    expect(fn () => Vote::factory()
        ->for($event, 'voteEvent')
        ->forDeputy($deputy)
        ->create())->toThrow(QueryException::class);
});

it('keeps the demo voting seed repeatable', function () {
    $this->seed(RollCallVotingDemoSeeder::class);
    $this->seed(RollCallVotingDemoSeeder::class);

    expect(CouncilOrganization::query()->count())->toBe(1)
        ->and(Convocation::query()->count())->toBe(1)
        ->and(CouncilSession::query()->count())->toBe(1)
        ->and(PlenaryMeeting::query()->count())->toBe(1)
        ->and(Motion::query()->count())->toBe(1)
        ->and(VoteEvent::query()->count())->toBe(1)
        ->and(Deputy::query()->count())->toBe(3)
        ->and(Vote::query()->count())->toBe(3);
});
