<?php

namespace App\Filament\Resources\Convocations\Resources\Deputies;

use App\Filament\Resources\Convocations\ConvocationResource;
use App\Filament\Resources\Convocations\Resources\Deputies\Pages\CreateDeputy;
use App\Filament\Resources\Convocations\Resources\Deputies\Pages\EditDeputy;
use App\Filament\Resources\Convocations\Resources\Deputies\Pages\ListDeputies;
use App\Filament\Resources\Deputies\RelationManagers\ContactPointsRelationManager;
use App\Filament\Resources\Deputies\Schemas\DeputyForm;
use App\Filament\Resources\Deputies\Tables\DeputiesTable;
use App\Models\Deputy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DeputyResource extends Resource
{
    protected static ?string $model = Deputy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $parentResource = ConvocationResource::class;

    protected static ?string $modelLabel = 'депутат';

    protected static ?string $pluralModelLabel = 'Депутати';

    protected static ?string $recordTitleAttribute = 'short_name';

    public static function form(Schema $schema): Schema
    {
        return DeputyForm::configure($schema);
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

    /**
     * @param  array<mixed>  $parameters
     */
    public static function getIndexUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
    ): string {
        return static::getUrl('index', $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
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
