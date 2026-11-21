<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TerritorialUnitCategory: string implements HasLabel
{
    case FirstLevel = 'O';
    case SpecialStatusCity = 'K';
    case SecondLevel = 'P';
    case ThirdLevel = 'H';
    case City = 'M';
    case Settlement = 'X';
    case Village = 'C';
    case CityDistrict = 'B';

    public function level(): int
    {
        return match ($this) {
            self::FirstLevel, self::SpecialStatusCity => 1,
            self::SecondLevel => 2,
            self::ThirdLevel => 3,
            self::City, self::Settlement, self::Village => 4,
            self::CityDistrict => 5,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::FirstLevel => 'Область',
            self::SpecialStatusCity => 'Місто зі спеціальним статусом',
            self::SecondLevel => 'Район',
            self::ThirdLevel => 'Громада',
            self::City => 'Місто',
            self::Settlement => 'Селище',
            self::Village => 'Село',
            self::CityDistrict => 'Район у місті',
        };
    }
}
