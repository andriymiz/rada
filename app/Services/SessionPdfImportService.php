<?php

namespace App\Services;

use App\Enums\ImportStatus;
use App\Enums\SourceDocumentStatus;
use App\Enums\StagedRecordStatus;
use App\Enums\VoteResult;
use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class SessionPdfImportService
{
    public function __construct(private readonly PdfTextExtractor $extractor) {}

    public function import(Import $import): int
    {
        $import->loadMissing('sourceDocument');
        $document = $import->sourceDocument;

        if ($import->session_number === null) {
            throw new InvalidArgumentException('A session number is required to import a PDF.');
        }

        $path = Storage::disk($document->disk)->path($document->path);

        if (! is_file($path)) {
            throw new RuntimeException('The uploaded PDF could not be found in storage.');
        }

        $pages = $this->extractor->pages($path);
        $sessionPages = array_values(array_filter($pages, static fn (string $page): bool => preg_match('/№\d+\(.*?\) №\d+/u', $page) === 1));

        if ($sessionPages === []) {
            DB::transaction(function () use ($document, $import): void {
                $import->update([
                    'status' => ImportStatus::Failed,
                    'notes' => 'Не вдалося знайти у PDF сторінки сесії для імпорту.',
                ]);
                $document->update(['status' => SourceDocumentStatus::Rejected]);
            });

            return 0;
        }

        return DB::transaction(function () use ($document, $import, $sessionPages): int {
            $records = 0;

            foreach ($sessionPages as $page) {
                preg_match('/№(\d+)\(.*?\) №(\d+)\s+(.*?)РІШЕННЯ\s+(НЕ\s+)?ПРИЙНЯТО/u', $page, $questionMatch);

                if (! isset($questionMatch[2])) {
                    $import->stagedRecords()->create([
                        'raw_payload' => ['page' => $page],
                        'status' => StagedRecordStatus::Rejected,
                        'validation_error' => 'Не вдалося розпізнати номер питання та його заголовок.',
                    ]);
                    $records++;

                    continue;
                }

                $votingResult = ($questionMatch[4] ?? '') === 'НЕ' ? 'Не прийнято' : 'Прийнято';

                preg_match_all(
                    '/([\p{L}’ʼ\'-]+\s+[А-ЯІЇЄҐA-Z]\.[А-ЯІЇЄҐA-Zа-яіїєґ]?\.?)\s*-\s*(Відсутній|Утримався|Не голосував|За|Проти)/u',
                    $page,
                    $votes,
                    PREG_SET_ORDER,
                );

                $pageRecords = 0;
                foreach ($votes as $vote) {
                    $name = trim($vote[1]);
                    $rawResult = $vote[2];
                    $import->stagedRecords()->updateOrCreate(
                        ['question_number' => $questionMatch[2], 'deputy_name' => $name],
                        [
                            'question_title' => trim($questionMatch[3]),
                            'voting_result' => $votingResult,
                            'original_name' => $name,
                            'raw_result' => $rawResult,
                            'recognized_result' => $this->result($rawResult),
                            'raw_payload' => ['page' => $page],
                            'status' => StagedRecordStatus::Pending,
                            'validation_error' => null,
                        ],
                    );
                    $records++;
                    $pageRecords++;
                }

                if ($pageRecords === 0) {
                    $import->stagedRecords()->create([
                        'question_number' => $questionMatch[2],
                        'question_title' => trim($questionMatch[3]),
                        'voting_result' => $votingResult,
                        'raw_payload' => ['page' => $page],
                        'status' => StagedRecordStatus::Rejected,
                        'validation_error' => 'Не знайдено жодного розпізнаного голосу.',
                    ]);
                    $records++;
                }
            }

            if ($records > 0) {
                $import->update(['status' => ImportStatus::NeedsReview]);
                $document->update(['status' => SourceDocumentStatus::Processed]);
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
