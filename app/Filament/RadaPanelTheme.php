<?php

namespace App\Filament;

use Filament\Enums\ThemeMode;
use Filament\FontProviders\LocalFontProvider;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Vite;

class RadaPanelTheme
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->viteTheme('resources/css/filament/rada/theme.css')
            ->brandName('Рада')
            ->defaultThemeMode(ThemeMode::Light)
            ->darkMode(false)
            ->colors([
                'primary' => [
                    50 => '#f5f5f5',
                    100 => '#e5e5e5',
                    200 => '#d4d4d4',
                    300 => '#262626',
                    400 => '#000000',
                    500 => '#000000',
                    600 => '#000000',
                    700 => '#000000',
                    800 => '#ffffff',
                    900 => '#000000',
                    950 => '#ffffff',
                ],
                'info' => Color::hex('#0A5DAA'),
                'success' => Color::hex('#00893E'),
            ])
            ->font(
                'e-Ukraine',
                url: Vite::asset('resources/css/fonts.css'),
                provider: LocalFontProvider::class,
            );
    }
}
