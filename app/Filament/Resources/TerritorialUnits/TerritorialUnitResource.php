<?php

namespace App\Filament\Resources\TerritorialUnits;

use App\Enums\TerritorialUnitCategory;
use App\Filament\Resources\TerritorialUnits\Pages\ListTerritorialUnits;
use App\Models\TerritorialUnit;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TerritorialUnitResource extends Resource
{
    protected static ?string $model = TerritorialUnit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static ?string $modelLabel = 'територіальна одиниця';

    protected static ?string $pluralModelLabel = 'КАТОТТГ';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('katottg_code')
                    ->label('Код')
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Категорія')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?TerritorialUnitCategory $state): ?string => $state?->getLabel()),
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('system_number');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTerritorialUnits::route('/'),
        ];
    }
}
