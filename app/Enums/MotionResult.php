<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MotionResult: string implements HasLabel
{
    case Passed = 'passed';
    case NotPassed = 'not_passed';
    case NotVoted = 'not_voted';
    case NotConsidered = 'not_considered';

    public function getLabel(): string
    {
        return match ($this) {
            self::Passed => 'Прийнято',
            self::NotPassed => 'Не прийнято',
            self::NotVoted => 'Не голосували',
            self::NotConsidered => 'Не розглядали',
        };
    }
}
