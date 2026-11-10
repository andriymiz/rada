<?php

namespace App\Filament\Resources\OpenData\Pages;

use App\Filament\Resources\OpenData\OpenDataResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpenData extends ListRecords
{
    protected static string $resource = OpenDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Додати ресурс відкритих даних'),
        ];
    }
}
