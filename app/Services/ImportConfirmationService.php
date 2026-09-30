<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Enums\StagedRecordStatus;
use App\Models\AuditLog;
use App\Models\CouncilSession;
use App\Models\Deputy;
use App\Models\Import;
use App\Models\Question;
use App\Models\RollCallVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportConfirmationService
{
    public function confirm(Import $import, User $user): void
    {
        DB::transaction(function () use ($import, $user): void {
            $import = Import::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();

            if ($import->status === ImportStatus::Confirmed) {
                return;
            }

            if ($import->status !== ImportStatus::NeedsReview) {
                throw new RuntimeException('Імпорт ще не готовий до підтвердження.');
            }

            $records = $import->stagedRecords()->lockForUpdate()->get();
            if ($records->isEmpty() || $records->contains(
                static fn ($record): bool => $record->status !== StagedRecordStatus::Pending
                    || $record->question_number === null
                    || $record->question_title === null
                    || $record->deputy_name === null
                    || $record->recognized_result === null,
            )) {
                throw new RuntimeException('Підтвердження неможливе: виправте критичні помилки у staging.');
            }

            $session = CouncilSession::firstOrCreate(
                ['session_number' => $import->session_number],
                ['title' => 'Сесія №'.$import->session_number, 'status' => 'held'],
            );

            foreach ($records as $record) {
                $question = Question::firstOrCreate(
                    ['session_id' => $session->id, 'question_number' => $record->question_number],
                    ['title' => $record->question_title],
                );
                $deputy = Deputy::firstOrCreate(
                    ['name' => $record->deputy_name],
                    ['is_active' => true],
                );

                RollCallVote::updateOrCreate(
                    ['question_id' => $question->id, 'deputy_id' => $deputy->id],
                    [
                        'original_name' => $record->original_name ?? $record->deputy_name,
                        'result' => $record->recognized_result,
                        'confirmed_by' => $user->id,
                        'confirmed_at' => now(),
                    ],
                );

                $record->update([
                    'question_id' => $question->id,
                    'deputy_id' => $deputy->id,
                    'status' => StagedRecordStatus::Confirmed,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            }

            $import->update([
                'session_id' => $session->id,
                'status' => ImportStatus::Confirmed,
            ]);
            $import->sourceDocument->update(['status' => SourceDocumentStatus::Processed]);

            AuditLog::create([
                'user_id' => $user->id,
                'event' => 'import.confirmed',
                'auditable_type' => Import::class,
                'auditable_id' => $import->id,
                'metadata' => ['records' => $records->count()],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }
}
