<?php

namespace Tests\Feature;

use App\Enums\VoteResult;
use App\Models\CouncilSession;
use App\Models\Deputy;
use App\Models\Question;
use App\Models\RollCallVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoteExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_vote_exports_require_authentication(): void
    {
        $this->get(route('exports.motions'))->assertRedirect(route('login'));
        $this->get(route('exports.votings'))->assertRedirect(route('login'));
    }

    public function test_motions_export_contains_agenda_items_and_confirmed_vote_totals(): void
    {
        $user = User::factory()->create();
        $session = CouncilSession::factory()->create([
            'session_number' => '42',
            'held_at' => '2026-09-30',
            'title' => 'Сесія ради',
        ]);
        $question = Question::factory()->create([
            'session_id' => $session->id,
            'question_number' => '7',
            'title' => 'Затвердження бюджету',
        ]);
        $emptyQuestion = Question::factory()->create([
            'session_id' => $session->id,
            'question_number' => '8',
            'title' => 'Наступне питання',
        ]);
        $deputy = Deputy::factory()->create([
            'external_id' => 'dep-123',
            'name' => 'Іваненко Іван',
        ]);
        config([
            'rada.open_data.authority_name' => 'Тестова рада',
            'rada.open_data.authority_id' => '01234567',
            'rada.open_data.authority_cattutc' => 'UA32140050000046018',
            'rada.open_data.convocation' => '8 скликання',
        ]);

        RollCallVote::create([
            'question_id' => $question->id,
            'deputy_id' => $deputy->id,
            'original_name' => 'Іваненко Іван',
            'result' => VoteResult::For,
            'notes' => 'Голос за',
            'confirmed_by' => $user->id,
            'confirmed_at' => '2026-09-30 12:00:00',
        ]);
        foreach ([
            VoteResult::Against,
            VoteResult::Abstained,
            VoteResult::NotVoted,
            VoteResult::Absent,
        ] as $result) {
            RollCallVote::create([
                'question_id' => $question->id,
                'deputy_id' => Deputy::factory()->create()->id,
                'original_name' => 'Депутат '.$result->value,
                'result' => $result,
                'confirmed_by' => $user->id,
                'confirmed_at' => '2026-09-30 12:00:00',
            ]);
        }
        $question->update([
            'project_number' => '757/2',
            'voting_result' => 'Прийнято',
            'decision_document_url' => 'https://rada.example.test/757-2',
            'decision_document_name' => '757-2.html',
        ]);
        RollCallVote::create([
            'question_id' => $question->id,
            'deputy_id' => Deputy::factory()->create()->id,
            'original_name' => 'Непідтверджений депутат',
            'result' => VoteResult::Against,
        ]);

        $response = $this->actingAs($user)->get(route('exports.motions'));

        $response->assertOk()
            ->assertDownload('motions.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame([
            'uid',
            'date',
            'authorityName',
            'authorityId',
            'authorityCattutc',
            'convocation',
            'legislativeSession',
            'number',
            'title',
            'projectNumber',
            'votingFor',
            'votingAgainst',
            'votingAbstain',
            'notVoting',
            'absent',
            'votingResult',
            'textUrl',
            'text',
        ], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame([
            '2026-09-30-'.$session->id.'-7',
            '2026-09-30',
            'Тестова рада',
            '01234567',
            'UA32140050000046018',
            '8 скликання',
            '42',
            '7',
            'Затвердження бюджету',
            '757/2',
            '1',
            '1',
            '1',
            '1',
            '1',
            'Прийнято',
            'https://rada.example.test/757-2',
            '757-2.html',
        ], $rows[1]);
        $this->assertSame([
            '2026-09-30-'.$session->id.'-8',
            '2026-09-30',
            'Тестова рада',
            '01234567',
            'UA32140050000046018',
            '8 скликання',
            '42',
            '8',
            'Наступне питання',
            '',
            '',
            '',
            '',
            '',
            'Не розглядали',
            '',
            '',
        ], $rows[2]);
    }

    public function test_votings_export_contains_confirmed_votes_linked_to_motions(): void
    {
        $user = User::factory()->create();
        $session = CouncilSession::factory()->create([
            'session_number' => '42',
            'held_at' => '2026-09-30',
        ]);
        $question = Question::factory()->create([
            'session_id' => $session->id,
            'question_number' => '7',
        ]);
        $results = [
            VoteResult::For->value => 'За',
            VoteResult::Against->value => 'Проти',
            VoteResult::Abstained->value => 'Утримався',
            VoteResult::NotVoted->value => 'Не голосував',
            VoteResult::Absent->value => 'Відсутній',
        ];
        $expectedRows = [['motionUid', 'voterId', 'voterName', 'motionTitle', 'result']];
        foreach ($results as $result => $label) {
            $deputy = Deputy::factory()->create([
                'external_id' => 'dep-'.$result,
                'name' => 'Депутат '.$result,
            ]);
            RollCallVote::create([
                'question_id' => $question->id,
                'deputy_id' => $deputy->id,
                'original_name' => $deputy->name,
                'result' => $result,
                'confirmed_by' => $user->id,
                'confirmed_at' => '2026-09-30 12:00:00',
            ]);
            $expectedRows[] = [
                '2026-09-30-'.$session->id.'-7',
                'dep-'.$result,
                'Депутат '.$result,
                $question->title,
                $label,
            ];
        }
        RollCallVote::create([
            'question_id' => $question->id,
            'deputy_id' => Deputy::factory()->create()->id,
            'original_name' => 'Непідтверджений депутат',
            'result' => VoteResult::Against,
        ]);

        $response = $this->actingAs($user)->get(route('exports.votings'));

        $response->assertOk()
            ->assertDownload('votings.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame($expectedRows, $rows);
        $this->assertStringNotContainsString('Непідтверджений депутат', $response->streamedContent());
    }

    private function parseCsv(string $content): array
    {
        return array_map(
            static fn (string $row): array => str_getcsv($row),
            array_filter(explode("\n", trim($content))),
        );
    }
}
