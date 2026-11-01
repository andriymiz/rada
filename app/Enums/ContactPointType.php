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

    public static function fromUrl(string $url): self
    {
        $url = mb_strtolower($url);

        return match (true) {
            str_contains($url, 'facebook.com') || str_contains($url, 'fb.com') => self::Facebook,
            str_contains($url, 'instagram.com') => self::Instagram,
            str_contains($url, 't.me') || str_contains($url, 'telegram.me') => self::Telegram,
            str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be') => self::YouTube,
            str_contains($url, 'tiktok.com') => self::TikTok,
            str_contains($url, 'twitter.com') || str_contains($url, 'x.com') => self::X,
            str_contains($url, 'linkedin.com') => self::LinkedIn,
            str_contains($url, 'wa.me') || str_contains($url, 'whatsapp.com') => self::WhatsApp,
            default => self::Website,
        };
    }

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
