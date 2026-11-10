<?php

namespace App\Filament\Resources\OpenData;

use App\Filament\Resources\OpenData\Pages\ListOpenData;
use App\Filament\Resources\OpenData\Schemas\OpenDataForm;
use App\Filament\Resources\OpenData\Tables\OpenDataTable;
use App\Models\OpenDataResource as OpenDataResourceModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OpenDataResource extends Resource
{
    protected static ?string $model = OpenDataResourceModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static ?string $modelLabel = 'ресурс відкритих даних';

    protected static ?string $pluralModelLabel = 'Відкриті дані';

    protected static ?string $recordTitleAttribute = 'code';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return OpenDataForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpenDataTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpenData::route('/'),
        ];
    }
}
