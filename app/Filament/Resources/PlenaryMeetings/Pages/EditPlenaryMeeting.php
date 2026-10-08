<?php

namespace App\Filament\Resources\PlenaryMeetings\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use Filament\Resources\Pages\EditRecord;

class EditPlenaryMeeting extends EditRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = PlenaryMeetingResource::class;
}
