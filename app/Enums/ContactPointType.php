<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactPointType: string implements HasLabel
{
    case Email = 'email';
    case Phone = 'phone';
    case Website = 'website';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Telegram = 'telegram';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case X = 'x';
    case LinkedIn = 'linkedin';
    case WhatsApp = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'Електронна пошта',
            self::Phone => 'Телефон',
            self::Website => 'Вебсайт',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Telegram => 'Telegram',
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::X => 'X',
            self::LinkedIn => 'LinkedIn',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
