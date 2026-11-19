<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Filament\Resources\Officials\Schemas\OfficialForm;
use App\Filament\Resources\Officials\Tables\OfficialsTable;
use App\Models\Employee;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OfficialsRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Працівники';

    protected static ?string $recordTitleAttribute = 'position';

    public function form(Schema $schema): Schema
    {
        return OfficialForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return OfficialsTable::configure($table)
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Employee $record): bool => $record->headed_units_count > 0)
                    ->tooltip(fn (Employee $record): ?string => $record->headed_units_count > 0 ? 'Спершу змініть керівника підрозділу.' : null),
            ]);
    }
}
