<?php

namespace App\Filament\Resources\Convocations\RelationManagers;

use App\Filament\Imports\DeputyImporter;
use App\Filament\Resources\Deputies\Schemas\DeputyForm;
use App\Filament\Resources\Deputies\Tables\DeputiesTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ImportAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeputiesRelationManager extends RelationManager
{
    protected static string $relationship = 'deputies';

    protected static ?string $title = 'Депутати';

    protected static ?string $recordTitleAttribute = 'short_name';

    public function table(Table $table): Table
    {
        return DeputiesTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['person', 'politicalParty']))
            ->headerActions([
                CreateAction::make()->schema(DeputyForm::components()),
                ImportAction::make()
                    ->label('Імпорт')
                    ->importer(DeputyImporter::class)
                    ->options(fn (): array => [
                        'convocationId' => $this->getOwnerRecord()->getKey(),
                        'convocation' => $this->getOwnerRecord(),
                    ]),
            ])
            ->recordActions([
                EditAction::make()->schema(DeputyForm::components()),
                DeleteAction::make(),
            ]);
    }
}
