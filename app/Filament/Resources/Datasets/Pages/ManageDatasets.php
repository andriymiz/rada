<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Enums\OpenDataResourceCode;
use App\Filament\Exports\DatasetExporter;
use App\Filament\Imports\DatasetImporter;
use App\Filament\Imports\ImportHelper;
use App\Filament\Resources\Datasets\DatasetResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageDatasets extends ManageRecords
{
    protected static string $resource = DatasetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Експорт')
                ->pluralModelLabel('наборів даних')
                ->exporter(DatasetExporter::class)
                ->fileName('register')
                ->columnMapping(false)
                ->formats([ExportFormat::Csv])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                    'publisher',
                    'responsibleOfficial.person',
                    'responsibleOfficial.contactPoints',
                ])),
            ImportAction::make()
                ->pluralModelLabel('наборів даних')
                ->importer(DatasetImporter::class)
                ->registerModalActions([
                    ImportHelper::downloadPortalResourceAction(OpenDataResourceCode::Register),
                ]),
            CreateAction::make(),
        ];
    }
}
