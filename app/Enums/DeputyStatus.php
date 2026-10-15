<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeputyStatus: int implements HasColor, HasLabel
{
    case Queued = 0;
    case Active = 1;
    case Departed = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => 'Очікує',
            self::Active => 'Чинний',
            self::Departed => 'Вибув',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Active => 'success',
            self::Departed => 'warning',
        };
    }
}
