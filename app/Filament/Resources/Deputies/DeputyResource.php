<?php

namespace App\Filament\Resources\Deputies;

use App\Filament\Resources\Deputies\Pages\CreateDeputy;
use App\Filament\Resources\Deputies\Pages\EditDeputy;
use App\Filament\Resources\Deputies\Pages\ListDeputies;
use App\Filament\Resources\Deputies\RelationManagers\ContactPointsRelationManager;
use App\Filament\Resources\Deputies\Schemas\DeputyForm;
use App\Filament\Resources\Deputies\Schemas\DeputyInfolist;
use App\Filament\Resources\Deputies\Tables\DeputiesTable;
use App\Models\Deputy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DeputyResource extends Resource
{
    protected static ?string $model = Deputy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'депутат';

    protected static ?string $pluralModelLabel = 'Депутати';

    protected static ?string $recordTitleAttribute = 'short_name';

    public static function form(Schema $schema): Schema
    {
        return DeputyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DeputyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeputiesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ContactPointsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeputies::route('/'),
            'create' => CreateDeputy::route('/create'),
            'edit' => EditDeputy::route('/{record}/edit'),
        ];
    }
}
