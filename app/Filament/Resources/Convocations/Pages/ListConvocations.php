<?php

namespace App\Filament\Resources\Convocations\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Convocations\ConvocationResource;
use App\Filament\Resources\Convocations\Schemas\ConvocationForm;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListConvocations extends ListRecords
{
    use HasRadaBreadcrumbs;

    protected static string $resource = ConvocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->schema(ConvocationForm::components()),
        ];
    }
}
