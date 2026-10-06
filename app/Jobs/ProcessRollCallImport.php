<?php

namespace App\Jobs;

use App\Enums\RollCallImportStatus;
use App\Models\RollCallImport;
use App\Notifications\RollCallImportProcessed;
use App\Services\RollCallPdfParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Throwable;

class ProcessRollCallImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public RollCallImport $rollCallImport,
    ) {}

    public function handle(RollCallPdfParser $parser): void
    {
        $this->rollCallImport->update([
            'status' => RollCallImportStatus::Processing,
        ]);

        Sleep::for(3)->seconds();

        $parsedResult = $parser->parse(Storage::disk('local')->get($this->rollCallImport->file_path));

        Log::info('Roll-call PDF parsed', [
            'roll_call_import_id' => $this->rollCallImport->id,
            'filename' => $this->rollCallImport->original_filename,
            'parsed_result' => $parsedResult,
        ]);

        $this->rollCallImport->update([
            'status' => RollCallImportStatus::Completed,
            'processed_at' => now(),
        ]);

        $this->rollCallImport->user->notify(new RollCallImportProcessed(
            $this->rollCallImport->original_filename,
            successful: true,
        ));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Roll-call PDF processing failed', [
            'roll_call_import_id' => $this->rollCallImport->id,
            'filename' => $this->rollCallImport->original_filename,
            'exception' => $exception,
        ]);

        $this->rollCallImport->update([
            'status' => RollCallImportStatus::Failed,
            'processed_at' => null,
        ]);

        $this->rollCallImport->user->notify(new RollCallImportProcessed(
            $this->rollCallImport->original_filename,
            successful: false,
        ));
    }
}
