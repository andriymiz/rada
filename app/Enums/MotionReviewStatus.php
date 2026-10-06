<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MotionReviewStatus: string implements HasColor, HasLabel
{
    case Approved = 'approved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Approved => 'Підтверджено',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Approved => 'success',
        };
    }
}
