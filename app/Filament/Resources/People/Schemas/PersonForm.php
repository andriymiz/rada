<?php

namespace App\Filament\Resources\People\Schemas;

use App\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class PersonForm
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
            Grid::make(3)
                ->columns(['sm' => 2, 'lg' => 3])
                ->schema([
                    TextInput::make('last_name')
                        ->label('Прізвище')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('first_name')
                        ->label('Ім’я')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('middle_name')
                        ->label('По батькові')
                        ->required()
                        ->maxLength(255),
                ])
                ->columnSpanFull(),
            Grid::make(2)
                ->columns(['sm' => 2])
                ->schema([
                    Select::make('gender')
                        ->label('Стать')
                        ->options(Gender::class)
                        ->rule(Rule::in(array_map(
                            static fn (Gender $gender): int => $gender->value,
                            Gender::cases(),
                        )))
                        ->required()
                        ->native(false),
                    DatePicker::make('date_of_birth')
                        ->label('Дата народження')
                        ->required()
                        ->native(false)
                        ->maxDate(now()),
                ])
                ->columnSpanFull(),
        ];
    }
}
