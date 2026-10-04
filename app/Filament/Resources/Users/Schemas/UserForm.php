<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Ім’я')
                    ->maxLength(255)
                    ->required(),
                TextInput::make('email')
                    ->label('Електронна пошта')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->required(),
                Select::make('role')
                    ->label('Роль')
                    ->options(UserRole::class)
                    ->default(UserRole::Employee)
                    ->required()
                    ->native(false),
                DateTimePicker::make('email_verified_at')
                    ->label('Пошту підтверджено'),
                TextInput::make('password')
                    ->label('Пароль')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
            ]);
    }
}
