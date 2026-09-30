<?php

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Enums\StagedRecordStatus;
use App\Enums\VoteResult;
use App\Models\Import;
use App\Models\SourceDocument;
use App\Models\StagedVoteRecord;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function createImport(): array
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

beforeEach(function () {
    Storage::fake('private');
});

test('valid staging records are confirmed atomically', function () {
    [$user, $import] = createImport();
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
});

test('invalid staging records cannot be confirmed', function () {
    [$user, $import] = createImport();
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
});

test('another user cannot view confirm or delete an import', function () {
    [$owner, $import] = createImport();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)->get(route('imports.show', $import))->assertForbidden();
    $this->actingAs($otherUser)->post(route('imports.confirm', $import))->assertForbidden();
    $this->actingAs($otherUser)->delete(route('imports.destroy', $import))->assertForbidden();

    $this->assertDatabaseHas('imports', ['id' => $import->id, 'uploaded_by' => $owner->id]);
    $this->assertDatabaseCount('roll_call_votes', 0);
});

test('cancel removes import document and staging records', function () {
    [$user, $import] = createImport();
    Storage::disk('private')->put($import->sourceDocument->path, 'pdf');
    StagedVoteRecord::create(['import_id' => $import->id, 'status' => StagedRecordStatus::Rejected]);

    $this->actingAs($user)->delete(route('imports.destroy', $import))
        ->assertRedirect(route('imports.index'));

    $this->assertDatabaseCount('imports', 0);
    $this->assertDatabaseCount('source_documents', 0);
    $this->assertDatabaseCount('staged_vote_records', 0);
    Storage::disk('private')->assertMissing('source-documents/test.pdf');
});
