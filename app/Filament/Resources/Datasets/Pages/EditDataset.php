<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Datasets\DatasetResource;
use App\Models\Dataset;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** @extends EditRecord<Dataset> */
class EditDataset extends EditRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = DatasetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
