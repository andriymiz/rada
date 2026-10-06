<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VoteOption: string implements HasLabel
{
    case Yes = 'yes';
    case No = 'no';
    case Abstain = 'abstain';
    case NotVoting = 'not_voting';
    case Absent = 'absent';
    case Paired = 'paired';

    public function getLabel(): string
    {
        return match ($this) {
            self::Yes => 'За',
            self::No => 'Проти',
            self::Abstain => 'Утримався',
            self::NotVoting => 'Не голосував',
            self::Absent => 'Відсутній',
            self::Paired => 'У парі',
        };
    }
}
