<?php

namespace App\Listeners;

use App\Filament\Exports\DatasetExporter;
use App\Jobs\UploadDatasetRegister;
use Filament\Actions\Exports\Models\Export;

class PrepareDatasetRegister
{
    public function onCreating(Export $export): void
    {
        $export->upload_to_portal = ($export->getOptions()['upload_to_portal'] ?? false) === true;
    }

    public function onUpdated(Export $export): void
    {
        if ($export->exporter !== DatasetExporter::class
            || ! $export->wasChanged('completed_at')
            || $export->completed_at === null
            || ! (bool) $export->upload_to_portal
            || $export->successful_rows !== $export->total_rows) {
            return;
        }

        UploadDatasetRegister::dispatch($export->withoutRelations())->afterCommit();
    }
}
