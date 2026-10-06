<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RollCallImportStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Queued => 'У черзі',
            self::Processing => 'Обробляється',
            self::Completed => 'Оброблено',
            self::Failed => 'Помилка',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
