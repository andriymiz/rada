<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Ім’я'),
                TextEntry::make('email')
                    ->label('Електронна пошта'),
                TextEntry::make('role')
                    ->label('Роль')
                    ->badge(),
                TextEntry::make('email_verified_at')
                    ->label('Пошту підтверджено')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Оновлено')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
