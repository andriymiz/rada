<?php

use App\Enums\MotionResult;
use App\Enums\VoteOption;
use App\Models\CouncilOrganization;
use App\Models\Membership;
use App\Models\Motion;
use App\Models\ParliamentaryConvocation;
use App\Models\ParliamentarySession;
use App\Models\Person;
use App\Models\PlenaryMeeting;
use App\Models\Vote;
use App\Models\VoteEvent;
use Database\Seeders\RollCallVotingDemoSeeder;
use Illuminate\Database\QueryException;

it('stores an agenda item without inventing a vote event', function () {
    $organization = CouncilOrganization::factory()->create();
    $convocation = ParliamentaryConvocation::factory()->create();
    $session = ParliamentarySession::factory()->for($convocation, 'convocation')->create();
    $meeting = PlenaryMeeting::factory()
        ->for($organization, 'organization')
        ->for($session, 'parliamentarySession')
        ->create(['date' => '2025-06-12']);
    $motion = Motion::factory()
        ->for($meeting, 'plenaryMeeting')
        ->create([
            'uid' => '2025-06-12-1',
            'number' => 1,
            'result' => MotionResult::NotConsidered,
        ]);

    expect($motion->plenaryMeeting->organization->edrpou)->toBe($organization->edrpou)
        ->and($motion->plenaryMeeting->parliamentarySession->convocation->is($convocation))->toBeTrue()
        ->and($motion->voteEvents)->toBeEmpty()
        ->and($motion->result)->toBe(MotionResult::NotConsidered);
});

it('allows a motion to have multiple distinct vote events', function () {
    $motion = Motion::factory()->create();

    $firstEvent = VoteEvent::factory()
        ->for($motion, 'motion')
        ->create(['identifier' => 'vote-1']);
    $secondEvent = VoteEvent::factory()
        ->for($motion, 'motion')
        ->create(['identifier' => 'vote-2']);
    $person = Person::factory()->create();

    Vote::factory()->for($firstEvent, 'voteEvent')->forPerson($person)->create();
    Vote::factory()->for($secondEvent, 'voteEvent')->forPerson($person)->create();

    expect($motion->voteEvents()->pluck('identifier')->all())
        ->toBe(['vote-1', 'vote-2'])
        ->and($firstEvent->motion->is($motion))->toBeTrue()
        ->and($secondEvent->motion->is($motion))->toBeTrue()
        ->and($person->votes()->count())->toBe(2);
});

it('stores one immutable voter snapshot per person for a recorded event', function () {
    $motion = Motion::factory()->create(['uid' => '2025-06-12-2']);
    $event = VoteEvent::factory()->for($motion, 'motion')->create();
    $person = Person::factory()->create([
        'name' => 'Іваненко Олександр Петрович',
        'voting_identifier' => 'COUNCIL-001',
    ]);
    Membership::factory()
        ->for($person, 'person')
        ->for($motion->plenaryMeeting->organization, 'organization')
        ->create();

    $vote = Vote::factory()
        ->for($event, 'voteEvent')
        ->for($person, 'person')
        ->create(['option' => VoteOption::Yes]);

    expect($event->votes)->toHaveCount(1)
        ->and($vote->person->is($person))->toBeTrue()
        ->and($vote->voter_identifier)->toBe('COUNCIL-001')
        ->and($vote->voter_name)->toBe('Іваненко Олександр Петрович')
        ->and($vote->option)->toBe(VoteOption::Yes);

    expect(fn () => Vote::factory()
        ->for($event, 'voteEvent')
        ->forPerson($person)
        ->create())->toThrow(QueryException::class);
});

it('keeps the demo voting seed repeatable', function () {
    $this->seed(RollCallVotingDemoSeeder::class);
    $this->seed(RollCallVotingDemoSeeder::class);

    expect(CouncilOrganization::query()->count())->toBe(1)
        ->and(ParliamentaryConvocation::query()->count())->toBe(1)
        ->and(ParliamentarySession::query()->count())->toBe(1)
        ->and(PlenaryMeeting::query()->count())->toBe(1)
        ->and(Motion::query()->count())->toBe(1)
        ->and(VoteEvent::query()->count())->toBe(1)
        ->and(Person::query()->count())->toBe(3)
        ->and(Membership::query()->count())->toBe(3)
        ->and(Vote::query()->count())->toBe(3);
});
