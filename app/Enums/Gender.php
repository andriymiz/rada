<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Uses the numeric codes from ISO/IEC 5218:2004, including 9 for not applicable.
 */
enum Gender: int implements HasLabel
{
    case Unknown = 0;
    case Male = 1;
    case Female = 2;
    case NotApplicable = 9;

    public function getLabel(): string
    {
        return match ($this) {
            self::Unknown => 'Невідомо',
            self::Male => 'Чоловік',
            self::Female => 'Жінка',
            self::NotApplicable => 'Не застосовується',
        };
    }
}
