<?php

namespace App\Filament\Resources\Convocations;

use App\Filament\Resources\Convocations\Pages\ListConvocations;
use App\Filament\Resources\Convocations\Schemas\ConvocationForm;
use App\Filament\Resources\Convocations\Tables\ConvocationsTable;
use App\Models\Convocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ConvocationResource extends Resource
{
    protected static ?string $model = Convocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'скликання';

    protected static ?string $pluralModelLabel = 'Скликання';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return ConvocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConvocationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConvocations::route('/'),
        ];
    }

    /**
     * @param  array<mixed>  $parameters
     */
    public static function getUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false, ?string $configuration = null): string
    {
        if (blank($name) || $name === 'index') {
            unset($parameters['record']);
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters, $configuration);
    }
}
