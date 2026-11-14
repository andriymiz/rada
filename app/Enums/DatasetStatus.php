<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DatasetStatus: string implements HasColor, HasLabel
{
    case Unknown = 'unknown';
    case NoInformation = 'no_information';
    case NotPublished = 'not_published';
    case Collecting = 'collecting';
    case Published = 'published';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unknown => 'Невідомо',
            self::NoInformation => 'Відсутня інформація',
            self::NotPublished => 'Не публікується',
            self::Collecting => 'Збирається інформація',
            self::Published => 'Опубліковано',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unknown => 'warning',
            self::NoInformation => 'gray',
            self::NotPublished => 'danger',
            self::Collecting => 'info',
            self::Published => 'success',
        };
    }
}
