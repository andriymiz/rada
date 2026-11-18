<?php

namespace App\Filament\Resources\Organizations\RelationManagers;

use App\Filament\Resources\Officials\Schemas\OfficialForm;
use App\Filament\Resources\Officials\Tables\OfficialsTable;
use App\Models\Official;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OfficialsRelationManager extends RelationManager
{
    protected static string $relationship = 'officials';

    protected static ?string $title = 'Посадовці';

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
                Action::make('markAsHead')
                    ->label('Призначити керівником')
                    ->icon(Heroicon::OutlinedStar)
                    ->iconButton()
                    ->visible(fn (Official $record): bool => ! $record->is_head)
                    ->action(function (Official $record): void {
                        $record->organization->setHead($record);
                    })
                    ->successNotificationTitle('Посадовця призначено керівником'),
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Official $record): bool => $record->headed_units_count > 0)
                    ->tooltip(fn (Official $record): ?string => $record->headed_units_count > 0 ? 'Спершу змініть керівника підрозділу.' : null),
            ]);
    }
}
