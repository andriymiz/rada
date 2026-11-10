<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OpenDataResourceCode: string implements HasLabel
{
    case Organizations = 'organizations';
    case Posts = 'posts';
    case Deputies = 'deputies';

    public function getLabel(): string
    {
        return match ($this) {
            self::Organizations => 'Організації (organizations)',
            self::Posts => 'Посадовці (posts)',
            self::Deputies => 'Депутати (deputies)',
        };
    }
}
