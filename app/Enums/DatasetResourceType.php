<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DatasetResourceType: string implements HasLabel
{
    case Organizations = 'organizations.csv';
    case Posts = 'posts.csv';
    case Deputies = 'deputies.csv';
    case Register = 'register.csv';
    case Reports = 'reports.zip';
    case ListOfReports = 'listOfReports.csv';

    public function getLabel(): string
    {
        return match ($this) {
            self::Organizations => 'Організації (organizations.csv)',
            self::Posts => 'Посадовці (posts.csv)',
            self::Deputies => 'Депутати (deputies.csv)',
            self::Register => 'Набори даних (register.csv)',
            self::Reports => 'Звіти (reports.zip)',
            self::ListOfReports => 'Перелік звітів (listOfReports.csv)',
        };
    }
}
