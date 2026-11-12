<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Enums\OpenDataResourceCode;
use App\Filament\Imports\DatasetImporter;
use App\Filament\Imports\ImportHelper;
use App\Filament\Resources\Datasets\DatasetResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDatasets extends ManageRecords
{
    protected static string $resource = DatasetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->pluralModelLabel('наборів даних')
                ->importer(DatasetImporter::class)
                ->registerModalActions([
                    ImportHelper::downloadPortalResourceAction(OpenDataResourceCode::Register),
                ]),
        ];
    }
}
