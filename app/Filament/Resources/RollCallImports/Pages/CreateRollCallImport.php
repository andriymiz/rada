<?php

namespace App\Filament\Resources\RollCallImports\Pages;

use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Jobs\ProcessRollCallImport;
use App\Models\RollCallImport;
use Filament\Resources\Pages\CreateRecord;

class CreateRollCallImport extends CreateRecord
{
    protected static string $resource = RollCallImportResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var RollCallImport $record */
        $record = $this->record;

        ProcessRollCallImport::dispatch($record);
    }

    protected function getRedirectUrl(): string
    {
        return RollCallImportResource::getUrl('index');
    }
}
