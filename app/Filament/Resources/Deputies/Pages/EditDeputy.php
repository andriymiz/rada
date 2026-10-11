<?php

namespace App\Filament\Resources\Deputies\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Deputies\DeputyResource;
use Filament\Resources\Pages\EditRecord;

class EditDeputy extends EditRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = DeputyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['person_id'], $data['convocation_id']);

        return $data;
    }
}
