<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DatasetUpdateFrequency: string implements HasLabel
{
    case MoreThanOncePerDay = 'more_than_once_per_day';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case EverySixMonths = 'every_six_months';
    case Yearly = 'yearly';

    public function getLabel(): string
    {
        return match ($this) {
            self::MoreThanOncePerDay => 'Більш як один раз на день',
            self::Daily => 'Щодня',
            self::Weekly => 'Щотижня',
            self::Monthly => 'Щомісяця',
            self::Quarterly => 'Щокварталу',
            self::EverySixMonths => 'Кожного півріччя',
            self::Yearly => 'Щороку',
        };
    }
}
