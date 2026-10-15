<?php

namespace App\Filament\Resources\Convocations\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Text;
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
                ->label('Назва')
                ->required()
                ->maxLength(255),
            FusedGroup::make([
                DatePicker::make('date_from')
                    ->label('Дата початку')
                    ->native(false)
                    ->placeholder('дд.мм.рррр')
                    ->columnSpan(['default' => 5, 'lg' => 5]),
                Text::make('—')
                    ->columnSpan(['default' => 2, 'lg' => 2]),
                DatePicker::make('date_until')
                    ->label('Дата закінчення')
                    ->native(false)
                    ->placeholder('дд.мм.рррр')
                    ->columnSpan(['default' => 5, 'lg' => 5]),
            ])
                ->label('Період')
                ->columns(['default' => 12, 'lg' => 12]),
        ];
    }
}
