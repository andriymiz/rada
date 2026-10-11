<?php

namespace App\Filament\Resources\Deputies\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Deputies\DeputyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDeputy extends CreateRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = DeputyResource::class;
}
