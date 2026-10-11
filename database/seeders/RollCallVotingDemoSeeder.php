<?php

namespace Database\Seeders;

use App\Enums\ContactPointType;
use App\Enums\Gender;
use App\Enums\MotionResult;
use App\Enums\VoteOption;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\CouncilSession;
use App\Models\Deputy;
use App\Models\Membership;
use App\Models\Motion;
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

        $convocation = Convocation::query()->firstOrCreate([
            'organization_id' => $organization->id,
            'name' => 'Демо скликання',
        ]);

        $session = CouncilSession::query()->firstOrCreate(
            [
                'convocation_id' => $convocation->id,
                'name' => 'Демо сесія',
            ],
        );

        $meeting = PlenaryMeeting::query()
            ->where('council_session_id', $session->id)
            ->whereDate('date', '2025-01-15')
            ->first();

        if (! $meeting) {
            $meeting = PlenaryMeeting::query()->create([
                'council_session_id' => $session->id,
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
            [$familyName, $firstName, $additionalName] = explode(' ', $name, 3);

            $deputy = Deputy::query()->firstOrCreate(
                [
                    'family_name' => $familyName,
                    'first_name' => $firstName,
                    'additional_name' => $additionalName,
                ],
                [
                    'gender' => Gender::Unknown,
                    'birth_date' => '1980-01-01',
                    'biography' => 'Демонстраційний депутат.',
                ],
            );

            $deputy->contactPoints()->firstOrCreate(
                ['type' => ContactPointType::Email],
                ['value' => 'demo@example.test', 'is_public' => true],
            );
            $deputy->contactPoints()->firstOrCreate(
                ['type' => ContactPointType::Phone],
                ['value' => '+380000000000', 'is_public' => true],
            );

            $membership = Membership::query()->firstOrCreate(
                [
                    'deputy_id' => $deputy->id,
                    'organization_id' => $organization->id,
                    'convocation_id' => $convocation->id,
                ],
                [
                    'voting_identifier' => $votingIdentifier,
                    'role' => 'Депутат міської ради',
                    'start_date' => '2020-11-01',
                ],
            );

            Vote::query()->firstOrCreate(
                [
                    'vote_event_id' => $voteEvent->id,
                    'deputy_id' => $deputy->id,
                ],
                [
                    'voter_identifier' => $membership->voting_identifier,
                    'voter_name' => $deputy->full_name,
                    'option' => $option,
                ],
            );
        }
    }
}
