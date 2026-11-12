<?php

namespace App\Filament\Resources\Officials\Pages;

use App\Enums\OpenDataResourceCode;
use App\Filament\Imports\ImportHelper;
use App\Filament\Imports\OfficialImporter;
use App\Filament\Resources\Officials\OfficialResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListOfficials extends ListRecords
{
    protected static string $resource = OfficialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->importer(OfficialImporter::class)
                ->registerModalActions([
                    ImportHelper::downloadPortalResourceAction(OpenDataResourceCode::Posts),
                ]),
        ];
    }
}
