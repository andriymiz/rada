<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = UserResource::class;
}
