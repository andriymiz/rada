<?php

namespace App\Filament\Resources\Convocations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class ConvocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(self::components());
    }

    /**
     * @return array<Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('label')
                ->label('Назва скликання')
                ->required()
                ->maxLength(255),
            DatePicker::make('date_from')
                ->label('Дата початку')
                ->native(false),
        ];
    }
}
