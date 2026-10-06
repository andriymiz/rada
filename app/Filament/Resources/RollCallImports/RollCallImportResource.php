<?php

namespace App\Filament\Resources\RollCallImports;

use App\Enums\RollCallImportStatus;
use App\Filament\Resources\RollCallImports\Pages\EditRollCallImport;
use App\Filament\Resources\RollCallImports\Pages\ListRollCallImports;
use App\Filament\Resources\RollCallImports\Pages\ViewRollCallImport;
use App\Filament\Resources\RollCallImports\Schemas\RollCallImportForm;
use App\Filament\Resources\RollCallImports\Schemas\RollCallImportInfolist;
use App\Filament\Resources\RollCallImports\Tables\RollCallImportsTable;
use App\Models\RollCallImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RollCallImportResource extends Resource
{
    protected static ?string $model = RollCallImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'імпорт голосувань';

    protected static ?string $pluralModelLabel = 'Імпорти голосувань';

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function form(Schema $schema): Schema
    {
        return RollCallImportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RollCallImportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RollCallImportsTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof RollCallImport
            && $record->status === RollCallImportStatus::AwaitingReview
            && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }

    public static function canDeleteAny(): bool
    {
        return true;
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof RollCallImport
            && $record->status === RollCallImportStatus::Completed
            && parent::canView($record);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRollCallImports::route('/'),
            'view' => ViewRollCallImport::route('/{record}'),
            'edit' => EditRollCallImport::route('/{record}/edit'),
        ];
    }
}
