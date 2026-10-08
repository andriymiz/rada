<?php

namespace App\Filament\Resources\PlenaryMeetings\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\PlenaryMeetings\Schemas\PlenaryMeetingForm;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlenaryMeetings extends ListRecords
{
    use HasRadaBreadcrumbs;

    protected static string $resource = PlenaryMeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->schema(PlenaryMeetingForm::components()),
        ];
    }
}
