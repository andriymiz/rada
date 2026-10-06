<?php

namespace App\Jobs;

use App\Enums\RollCallImportStatus;
use App\Models\RollCallImport;
use App\Notifications\RollCallImportProcessed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Sleep;
use Throwable;

class ProcessRollCallImport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public RollCallImport $rollCallImport,
    ) {}

    public function handle(): void
    {
        $this->rollCallImport->update([
            'status' => RollCallImportStatus::Processing,
        ]);

        Sleep::for(3)->seconds();

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
