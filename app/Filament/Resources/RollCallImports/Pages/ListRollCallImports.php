<?php

namespace App\Filament\Resources\RollCallImports\Pages;

use App\Filament\Resources\RollCallImports\RollCallImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRollCallImports extends ListRecords
{
    protected static string $resource = RollCallImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Новий імпорт'),
        ];
    }
}
