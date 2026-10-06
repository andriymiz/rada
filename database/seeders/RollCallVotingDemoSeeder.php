<?php

namespace Database\Seeders;

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
use Illuminate\Database\Seeder;

class RollCallVotingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $organization = CouncilOrganization::query()->firstOrCreate(
            ['edrpou' => 'TEST0001'],
            [
                'name' => 'Демонстраційна міська рада',
                'katoottg' => 'UA00000000000000000',
            ],
        );

        $convocation = ParliamentaryConvocation::query()->firstOrCreate([
            'name' => 'Демо скликання',
        ]);

        $session = ParliamentarySession::query()->firstOrCreate(
            [
                'convocation_id' => $convocation->id,
                'name' => 'Демо сесія',
            ],
        );

        $meeting = PlenaryMeeting::query()
            ->where('organization_id', $organization->id)
            ->where('parliamentary_session_id', $session->id)
            ->whereDate('date', '2025-01-15')
            ->first();

        if (! $meeting) {
            $meeting = PlenaryMeeting::query()->create([
                'organization_id' => $organization->id,
                'parliamentary_session_id' => $session->id,
                'date' => '2025-01-15',
            ]);
        }

        $motion = Motion::query()->firstOrCreate(
            ['uid' => '2025-01-15-1'],
            [
                'plenary_meeting_id' => $meeting->id,
                'number' => 1,
                'title' => 'Демонстраційне питання порядку денного',
                'result' => MotionResult::Passed,
                'text' => 'demo-decision.html',
            ],
        );

        $voteEvent = VoteEvent::query()->firstOrCreate(
            ['motion_id' => $motion->id],
            [
                'identifier' => 'demo-vote-1',
                'result' => 'Прийнято',
            ],
        );

        foreach ([
            ['DEMO-VOTER-001', 'Демонстраційний Депутат Один', VoteOption::Yes],
            ['DEMO-VOTER-002', 'Демонстраційний Депутат Два', VoteOption::No],
            ['DEMO-VOTER-003', 'Демонстраційний Депутат Три', VoteOption::Abstain],
        ] as [$votingIdentifier, $name, $option]) {
            $person = Person::query()->firstOrCreate(
                ['voting_identifier' => $votingIdentifier],
                ['name' => $name],
            );

            $membership = Membership::query()
                ->where('person_id', $person->id)
                ->where('organization_id', $organization->id)
                ->where('role', 'Депутат міської ради')
                ->whereDate('start_date', '2020-11-01')
                ->first();

            if (! $membership) {
                Membership::query()->create([
                    'person_id' => $person->id,
                    'organization_id' => $organization->id,
                    'role' => 'Депутат міської ради',
                    'start_date' => '2020-11-01',
                ]);
            }

            Vote::query()->firstOrCreate(
                [
                    'vote_event_id' => $voteEvent->id,
                    'person_id' => $person->id,
                ],
                [
                    'voter_identifier' => $person->voting_identifier,
                    'voter_name' => $person->name,
                    'option' => $option,
                ],
            );
        }
    }
}
