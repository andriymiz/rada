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

        RollCallVote::create([
            'question_id' => $question->id,
            'deputy_id' => $deputy->id,
            'original_name' => 'Іваненко Іван',
            'result' => VoteResult::For,
            'notes' => 'Голос за',
            'confirmed_by' => $user->id,
            'confirmed_at' => '2026-09-30 12:00:00',
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
            'motion_id',
            'session_id',
            'session_number',
            'session_date',
            'session_title',
            'motion_number',
            'motion_text',
            'votes_total',
            'votes_for',
            'votes_against',
            'votes_abstained',
            'votes_not_voted',
            'votes_absent',
            'votes_unknown',
        ], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame([
            (string) $question->id,
            (string) $session->id,
            '42',
            '2026-09-30',
            'Сесія ради',
            '7',
            'Затвердження бюджету',
            '1',
            '1',
            '0',
            '0',
            '0',
            '0',
            '0',
        ], $rows[1]);
        $this->assertSame([
            (string) $emptyQuestion->id,
            (string) $session->id,
            '42',
            '2026-09-30',
            'Сесія ради',
            '8',
            'Наступне питання',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
        ], $rows[2]);
    }

    public function test_votings_export_contains_confirmed_votes_linked_to_motions(): void
    {
        $user = User::factory()->create();
        $question = Question::factory()->create();
        $deputy = Deputy::factory()->create([
            'external_id' => 'dep-123',
            'name' => 'Іваненко Іван',
        ]);

        RollCallVote::create([
            'question_id' => $question->id,
            'deputy_id' => $deputy->id,
            'original_name' => 'Іваненко Іван',
            'result' => VoteResult::For,
            'confirmed_by' => $user->id,
            'confirmed_at' => '2026-09-30 12:00:00',
        ]);
        RollCallVote::create([
            'question_id' => $question->id,
            'deputy_id' => Deputy::factory()->create()->id,
            'original_name' => 'Непідтверджений депутат',
            'result' => VoteResult::Against,
        ]);

        $response = $this->actingAs($user)->get(route('exports.votings'));

        $response->assertOk()->assertDownload('votings.csv');

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame([
            ['voting_id', 'motion_id', 'deputy_external_id', 'deputy_name', 'vote'],
            [
                '1',
                (string) $question->id,
                'dep-123',
                'Іваненко Іван',
                'for',
            ],
        ], $rows);
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
