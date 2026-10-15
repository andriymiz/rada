<?php

namespace App\Filament\Resources\Convocations\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\Convocations\ConvocationResource;
use App\Models\Convocation;
use Filament\Resources\Pages\EditRecord;

/** @extends EditRecord<Convocation> */
class EditConvocation extends EditRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = ConvocationResource::class;
}
