<?php

namespace App\Filament\Resources\Deputies\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Deputies\DeputyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeputies extends ListRecords
{
    use HasRadaBreadcrumbs;

    protected static string $resource = DeputyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
