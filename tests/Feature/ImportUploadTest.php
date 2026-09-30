<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Models\AuditLog;
use App\Models\SourceDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_authenticated_user_can_upload_a_pdf_to_private_storage(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('imports.store'), [
            'document' => UploadedFile::fake()->create('minutes.pdf', 50, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $document = SourceDocument::firstOrFail();

        $this->assertSame(SourceDocumentStatus::Uploaded, $document->status);
        $this->assertSame(ImportStatus::Pending, $document->imports()->firstOrFail()->status);
        Storage::disk('private')->assertExists($document->path);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'source_document.uploaded',
            'user_id' => $user->id,
        ]);
    }

    public function test_identical_pdf_cannot_be_uploaded_twice(): void
    {
        $user = User::factory()->create();
        $payload = '%PDF-1.4 sample content';

        $this->actingAs($user)->post(route('imports.store'), [
            'document' => UploadedFile::fake()->createWithContent('first.pdf', $payload),
        ])->assertRedirect();

        $this->actingAs($user)->from(route('imports.create'))->post(route('imports.store'), [
            'document' => UploadedFile::fake()->createWithContent('second.pdf', $payload),
        ])->assertSessionHasErrors('document');

        $this->assertDatabaseCount('source_documents', 1);
        $this->assertDatabaseCount('imports', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_non_pdf_upload_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('imports.create'))
            ->post(route('imports.store'), [
                'document' => UploadedFile::fake()->createWithContent('notes.txt', 'not a PDF'),
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('source_documents', 0);
    }
}
