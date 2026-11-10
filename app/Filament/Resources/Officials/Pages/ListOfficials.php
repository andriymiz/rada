<?php

namespace App\Filament\Resources\Officials\Pages;

use App\Enums\OpenDataResourceCode;
use App\Filament\Imports\OfficialImporter;
use App\Filament\Resources\Officials\OfficialResource;
use App\Services\OpenDataPortal;
use Filament\Actions\Action;
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
                ->label('Імпорт CSV')
                ->importer(OfficialImporter::class)
                ->registerModalActions([
                    function (): ?Action {
                        $downloadUrl = app(OpenDataPortal::class)
                            ->getResourceDownloadUrl(OpenDataResourceCode::Posts);

                        if ($downloadUrl === null) {
                            return null;
                        }

                        return Action::make('downloadExample')
                            ->label('Завантажити актуальний CSV з порталу відкритих даних')
                            ->link()
                            ->url($downloadUrl, true);
                    },
                ]),
        ];
    }
}
