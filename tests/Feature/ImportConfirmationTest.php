<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Enums\StagedRecordStatus;
use App\Enums\VoteResult;
use App\Models\Import;
use App\Models\SourceDocument;
use App\Models\StagedVoteRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_valid_staging_records_are_confirmed_atomically(): void
    {
        [$user, $import] = $this->createImport();
        $record = StagedVoteRecord::create([
            'import_id' => $import->id,
            'question_number' => '1',
            'question_title' => 'Питання',
            'voting_result' => 'Прийнято',
            'deputy_name' => 'Іваненко І.І.',
            'original_name' => 'Іваненко І.І.',
            'raw_result' => 'За',
            'recognized_result' => VoteResult::For,
            'status' => StagedRecordStatus::Pending,
        ]);

        $this->actingAs($user)->post(route('imports.confirm', $import))
            ->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseCount('roll_call_votes', 1);
        $this->assertDatabaseHas('imports', ['id' => $import->id, 'status' => ImportStatus::Confirmed->value]);
        $this->assertDatabaseHas('questions', [
            'question_number' => '1',
            'voting_result' => 'Прийнято',
        ]);
        $this->assertDatabaseHas('staged_vote_records', [
            'id' => $record->id,
            'status' => StagedRecordStatus::Confirmed->value,
        ]);

        $this->actingAs($user)->post(route('imports.confirm', $import));
        $this->assertDatabaseCount('roll_call_votes', 1);
    }

    public function test_invalid_staging_records_cannot_be_confirmed(): void
    {
        [$user, $import] = $this->createImport();
        StagedVoteRecord::create([
            'import_id' => $import->id,
            'status' => StagedRecordStatus::Rejected,
            'validation_error' => 'Некоректний рядок',
        ]);

        $this->actingAs($user)->from(route('imports.show', $import))
            ->post(route('imports.confirm', $import))
            ->assertSessionHasErrors('import');

        $this->assertDatabaseCount('roll_call_votes', 0);
        $this->assertDatabaseHas('imports', ['id' => $import->id, 'status' => ImportStatus::NeedsReview->value]);
    }

    public function test_cancel_removes_import_document_and_staging_records(): void
    {
        [$user, $import] = $this->createImport();
        Storage::disk('private')->put($import->sourceDocument->path, 'pdf');
        StagedVoteRecord::create(['import_id' => $import->id, 'status' => StagedRecordStatus::Rejected]);

        $this->actingAs($user)->delete(route('imports.destroy', $import))
            ->assertRedirect(route('imports.index'));

        $this->assertDatabaseCount('imports', 0);
        $this->assertDatabaseCount('source_documents', 0);
        $this->assertDatabaseCount('staged_vote_records', 0);
        Storage::disk('private')->assertMissing('source-documents/test.pdf');
    }

    private function createImport(): array
    {
        $user = User::factory()->create();
        $document = SourceDocument::create([
            'uploaded_by' => $user->id,
            'disk' => 'private',
            'path' => 'source-documents/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size' => 3,
            'sha256' => str_repeat((string) random_int(0, 9), 64),
            'status' => SourceDocumentStatus::Uploaded,
        ]);
        $import = Import::create([
            'source_document_id' => $document->id,
            'uploaded_by' => $user->id,
            'session_number' => '99',
            'status' => ImportStatus::NeedsReview,
        ]);

        return [$user, $import];
    }
}
