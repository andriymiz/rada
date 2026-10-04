<?php

namespace App\Filament\Public\Pages;

use Filament\Pages\SimplePage;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class Landing extends SimplePage
{
    protected static ?string $title = 'Зборівська громада';

    protected string $view = 'filament.public.pages.landing';

    protected Width|string|null $maxWidth = Width::FourExtraLarge;

    protected bool $hasTopbar = false;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
