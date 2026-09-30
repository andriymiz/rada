<?php

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Models\SourceDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
});

test('authenticated user can upload a pdf to private storage', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('imports.store'), [
        'document' => UploadedFile::fake()->create('minutes.pdf', 50, 'application/pdf'),
        'session_number' => 'test-01',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $document = SourceDocument::firstOrFail();

    $this->assertSame(SourceDocumentStatus::Rejected, $document->status);
    $this->assertSame(ImportStatus::Failed, $document->imports()->firstOrFail()->status);
    Storage::disk('private')->assertExists($document->path);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'source_document.uploaded',
        'user_id' => $user->id,
    ]);
});

test('identical pdf cannot be uploaded twice', function () {
    $user = User::factory()->create();
    $payload = '%PDF-1.4 sample content';

    $this->actingAs($user)->post(route('imports.store'), [
        'document' => UploadedFile::fake()->createWithContent('first.pdf', $payload),
        'session_number' => 'test-01',
    ])->assertRedirect();

    $this->actingAs($user)->from(route('imports.create'))->post(route('imports.store'), [
        'document' => UploadedFile::fake()->createWithContent('second.pdf', $payload),
        'session_number' => 'test-01',
    ])->assertSessionHasErrors('document');

    $this->assertDatabaseCount('source_documents', 1);
    $this->assertDatabaseCount('imports', 1);
    $this->assertDatabaseCount('audit_logs', 1);
});

test('non pdf upload is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('imports.create'))
        ->post(route('imports.store'), [
            'document' => UploadedFile::fake()->createWithContent('notes.txt', 'not a PDF'),
            'session_number' => 'test-01',
        ])
        ->assertSessionHasErrors('document');

    $this->assertDatabaseCount('source_documents', 0);
});
