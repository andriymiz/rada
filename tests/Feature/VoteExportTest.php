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

    public function test_vote_export_requires_authentication(): void
    {
        $this->get(route('exports.votes'))->assertRedirect(route('login'));
    }

    public function test_csv_export_contains_confirmed_votes_and_excludes_unconfirmed_votes(): void
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

        $response = $this->actingAs($user)->get(route('exports.votes'));

        $response->assertOk()
            ->assertDownload('roll-call-votes.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $rows = array_map(
            static fn (string $row): array => str_getcsv($row),
            array_filter(explode("\n", trim($response->streamedContent()))),
        );

        $this->assertSame([
            'session_number',
            'session_date',
            'session_title',
            'question_number',
            'question_title',
            'deputy_external_id',
            'deputy_name',
            'result',
            'notes',
            'confirmed_at',
        ], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame([
            '42',
            '2026-09-30',
            'Сесія ради',
            '7',
            'Затвердження бюджету',
            'dep-123',
            'Іваненко Іван',
            'for',
            'Голос за',
            '2026-09-30 12:00:00',
        ], $rows[1]);
        $this->assertStringNotContainsString('Непідтверджений депутат', $response->streamedContent());
    }
}
