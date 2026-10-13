<?php

namespace App\Filament\Resources\Convocations\Resources\Deputies\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Imports\DeputyImporter;
use App\Filament\Resources\Convocations\Resources\Deputies\DeputyResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListDeputies extends ListRecords
{
    use HasRadaBreadcrumbs;

    protected static string $resource = DeputyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ImportAction::make()
                ->label('Імпорт')
                ->importer(DeputyImporter::class)
                ->options(fn (): array => [
                    'convocationId' => $this->getParentRecord()?->getKey(),
                    'convocation' => $this->getParentRecord(),
                ]),
        ];
    }
}
