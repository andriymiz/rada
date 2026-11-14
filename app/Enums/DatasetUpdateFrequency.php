<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DatasetUpdateFrequency: string implements HasLabel
{
    case MoreThanOncePerDay = 'more than once a day';
    case Daily = 'once a day';
    case Weekly = 'once a week';
    case Monthly = 'once a month';
    case Quarterly = 'once a quarter';
    case EverySixMonths = 'once a half year';
    case Yearly = 'once a year';
    case NoLongerUpdated = 'no longer updated';

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
            self::NoLongerUpdated => 'Більше не оновлюється',
        };
    }
}
