<?php

namespace App\Filament\Resources\RollCallImports;

use App\Enums\RollCallImportStatus;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\RollCallImports\Pages\EditRollCallImport;
use App\Filament\Resources\RollCallImports\Pages\ViewRollCallImport;
use App\Filament\Resources\RollCallImports\Schemas\RollCallImportForm;
use App\Filament\Resources\RollCallImports\Schemas\RollCallImportInfolist;
use App\Models\RollCallImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class RollCallImportResource extends Resource
{
    protected static ?string $model = RollCallImport::class;

    protected static ?string $slug = 'meetings/imports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'імпорт';

    protected static ?string $pluralModelLabel = 'Імпорти';

    protected static ?string $navigationParentItem = 'Засідання';

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getIndexUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
    ): string {
        return PlenaryMeetingResource::getUrl(
            'index',
            $parameters,
            $isAbsolute,
            $panel,
            $tenant,
            $shouldGuessMissingParameters,
        );
    }

    public static function form(Schema $schema): Schema
    {
        return RollCallImportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RollCallImportInfolist::configure($schema);
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
            'view' => ViewRollCallImport::route('/{record}'),
            'edit' => EditRollCallImport::route('/{record}/edit'),
        ];
    }
}
