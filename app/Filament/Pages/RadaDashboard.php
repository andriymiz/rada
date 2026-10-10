<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;
use Illuminate\Contracts\Support\Htmlable;

class RadaDashboard extends Dashboard
{
    public function getTitle(): string|Htmlable
    {
        $name = auth()->user()?->name;

        return "Вітаємо, {$name}";
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
