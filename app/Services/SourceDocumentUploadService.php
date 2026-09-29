<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Models\AuditLog;
use App\Models\Import;
use App\Models\SourceDocument;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class SourceDocumentUploadService
{
    public function upload(UploadedFile $file, User $user, ?int $sessionId = null): Import
    {
        $sha256 = hash_file('sha256', $file->getRealPath());

        if ($sha256 === false) {
            throw new RuntimeException('Could not calculate the uploaded file hash.');
        }

        if (SourceDocument::where('sha256', $sha256)->exists()) {
            throw ValidationException::withMessages([
                'document' => 'Цей PDF уже завантажено.',
            ]);
        }

        $path = $file->store('source-documents', 'private');

        if ($path === false) {
            throw new RuntimeException('Could not store the uploaded PDF.');
        }

        try {
            return DB::transaction(function () use ($file, $user, $sessionId, $sha256, $path): Import {
                $document = SourceDocument::create([
                    'uploaded_by' => $user->id,
                    'disk' => 'private',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/pdf',
                    'size' => $file->getSize(),
                    'sha256' => $sha256,
                    'status' => SourceDocumentStatus::Uploaded,
                ]);

                $import = Import::create([
                    'source_document_id' => $document->id,
                    'session_id' => $sessionId,
                    'uploaded_by' => $user->id,
                    'status' => ImportStatus::Pending,
                ]);

                AuditLog::create([
                    'user_id' => $user->id,
                    'event' => 'source_document.uploaded',
                    'auditable_type' => SourceDocument::class,
                    'auditable_id' => $document->id,
                    'metadata' => [
                        'sha256' => $sha256,
                        'import_id' => $import->id,
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);

                return $import;
            });
        } catch (Throwable $exception) {
            Storage::disk('private')->delete($path);

            if ($exception instanceof QueryException && SourceDocument::where('sha256', $sha256)->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'Цей PDF уже завантажено.',
                ]);
            }

            throw $exception;
        }
    }
}
