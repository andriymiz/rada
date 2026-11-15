<?php

namespace App\Filament\Resources\Datasets\RelationManagers;

use App\Filament\Resources\DatasetResources\Schemas\DatasetResourceInfolist;
use App\Filament\Resources\DatasetResources\Tables\DatasetResourcesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ResourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'resources';

    protected static ?string $title = 'Ресурси набору даних';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return filled($ownerRecord->getAttribute('external_id'))
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function infolist(Schema $schema): Schema
    {
        return DatasetResourceInfolist::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DatasetResourcesTable::configure($table);
    }
}
