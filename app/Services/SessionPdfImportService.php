<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Enums\StagedRecordStatus;
use App\Enums\VoteResult;
use App\Models\Import;
use Illuminate\Support\Facades\DB;

class SessionPdfImportService
{
    public function __construct(private readonly PdfTextExtractor $extractor) {}

    public function import(Import $import): int
    {
        $path = $import->sourceDocument->disk === 'private'
            ? storage_path('app/private/'.$import->sourceDocument->path)
            : storage_path('app/'.$import->sourceDocument->path);

        if (! is_file($path)) {
            return 0;
        }

        $pages = $this->extractor->pages($path);
        $sessionPages = array_values(array_filter($pages, static fn (string $page): bool => preg_match('/№\d+\(.*?\) №\d+/u', $page) === 1));

        if ($sessionPages === []) {
            return 0;
        }

        return DB::transaction(function () use ($import, $sessionPages): int {
            if ($import->session_number === null) {
                return 0;
            }

            $records = 0;

            foreach ($sessionPages as $page) {
                preg_match('/№(\d+)\(.*?\) №(\d+)\s+(.*?)РІШЕННЯ\s+(?:НЕ\s+)?ПРИЙНЯТО/u', $page, $questionMatch);

                if (! isset($questionMatch[2])) {
                    $import->stagedRecords()->create([
                        'raw_payload' => ['page' => $page],
                        'status' => StagedRecordStatus::Rejected,
                        'validation_error' => 'Не вдалося розпізнати номер питання та його заголовок.',
                    ]);
                    $records++;
                    continue;
                }

                preg_match_all(
                    '/([\p{L}’ʼ\'-]+\s+[А-ЯІЇЄҐA-Z]\.[А-ЯІЇЄҐA-Zа-яіїєґ]?\.?)\s*-\s*(Відсутній|Утримався|Не голосував|За|Проти)/u',
                    $page,
                    $votes,
                    PREG_SET_ORDER,
                );

                foreach ($votes as $vote) {
                    $name = trim($vote[1]);
                    $rawResult = $vote[2];
                    $import->stagedRecords()->updateOrCreate(
                        ['question_number' => $questionMatch[2], 'deputy_name' => $name],
                        [
                            'question_title' => trim($questionMatch[3]),
                            'original_name' => $name,
                            'raw_result' => $rawResult,
                            'recognized_result' => $this->result($rawResult),
                            'raw_payload' => ['page' => $page],
                            'status' => StagedRecordStatus::Pending,
                            'validation_error' => null,
                        ],
                    );
                    $records++;
                }
            }

            if ($records > 0) {
                $import->update(['status' => ImportStatus::NeedsReview]);
                $import->sourceDocument->update(['status' => SourceDocumentStatus::Processed]);
            }

            return $records;
        });
    }

    private function result(string $rawResult): VoteResult
    {
        return match ($rawResult) {
            'За' => VoteResult::For,
            'Проти' => VoteResult::Against,
            'Утримався' => VoteResult::Abstained,
            'Не голосував' => VoteResult::NotVoted,
            'Відсутній' => VoteResult::Absent,
            default => VoteResult::Unknown,
        };
    }
}
