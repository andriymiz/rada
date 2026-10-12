<?php

namespace App\Filament\Resources\Convocations\Resources\Deputies\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Convocations\Resources\Deputies\DeputyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeputy extends EditRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = DeputyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

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
