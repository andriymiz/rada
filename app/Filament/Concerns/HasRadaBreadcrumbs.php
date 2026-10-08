<?php

namespace App\Filament\Concerns;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

trait HasRadaBreadcrumbs
{
    /**
     * @return array<string, string|Htmlable>
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();

        if ($this instanceof ListRecords) {
            if (static::getResource()::getNavigationParentItem() === null) {
                return [];
            }

            array_pop($breadcrumbs);

            return $breadcrumbs;
        }

        if ($this instanceof EditRecord || $this instanceof ViewRecord) {
            array_pop($breadcrumbs);
            array_pop($breadcrumbs);
        }

        return $breadcrumbs;
    }
}
