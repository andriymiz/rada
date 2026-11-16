<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Organizations\OrganizationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganization extends CreateRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = OrganizationResource::class;
}
