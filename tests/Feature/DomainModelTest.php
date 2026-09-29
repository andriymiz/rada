<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Enums\VoteResult;
use App\Models\CouncilSession;
use App\Models\Department;
use App\Models\Deputy;
use App\Models\Question;
use App\Models\RollCallVote;
use App\Models\SourceDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_vote_result_enum_includes_all_required_values(): void
    {
        $this->assertSame(
            ['for', 'against', 'abstained', 'not_voted', 'absent', 'unknown'],
            array_map(static fn (VoteResult $result): string => $result->value, VoteResult::cases()),
        );
        $this->assertSame('pending', ImportStatus::Pending->value);
        $this->assertSame('uploaded', SourceDocumentStatus::Uploaded->value);
    }

    public function test_initial_domain_models_are_related_and_preserve_raw_names(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $department->id]);
        $session = CouncilSession::factory()->create();
        $question = Question::factory()->create(['session_id' => $session->id]);
        $deputy = Deputy::factory()->create();

        $document = SourceDocument::create([
            'uploaded_by' => $user->id,
            'disk' => 'private',
            'path' => 'source-documents/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 20,
            'sha256' => str_repeat('a', 64),
            'status' => SourceDocumentStatus::Uploaded,
        ]);

        $vote = RollCallVote::create([
            'question_id' => $question->id,
            'deputy_id' => $deputy->id,
            'original_name' => 'Розпізнане ім’я з джерела',
            'result' => VoteResult::For,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        $this->assertSame($department->id, $user->department->id);
        $this->assertSame($session->id, $question->session->id);
        $this->assertSame($deputy->id, $vote->deputy->id);
        $this->assertSame('Розпізнане ім’я з джерела', $vote->original_name);
        $this->assertSame($user->id, $document->uploader->id);
    }
}
