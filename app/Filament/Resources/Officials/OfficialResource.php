<?php

namespace App\Filament\Resources\Officials;

use App\Filament\Resources\Officials\Pages\ListOfficials;
use App\Filament\Resources\Officials\Schemas\OfficialForm;
use App\Filament\Resources\Officials\Tables\OfficialsTable;
use App\Models\Official;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OfficialResource extends Resource
{
    protected static ?string $model = Official::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'посадовець';

    protected static ?string $pluralModelLabel = 'Посадовці';

    protected static ?string $recordTitleAttribute = 'position';

    public static function form(Schema $schema): Schema
    {
        return OfficialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfficialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOfficials::route('/'),
        ];
    }
}
