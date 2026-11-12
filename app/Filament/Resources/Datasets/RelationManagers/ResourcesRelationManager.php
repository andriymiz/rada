<?php

namespace App\Filament\Resources\Datasets\RelationManagers;

use App\Enums\DatasetResourceType;
use App\Models\DatasetResource;
use App\Services\OpenDataPortal;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class ResourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'resources';

    protected static ?string $title = 'Ресурси набору даних';

    protected static ?string $recordTitleAttribute = 'external_id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('external_id')
                    ->label('Ресурс на порталі')
                    ->searchable(false)
                    ->required()
                    ->options(function (RelationManager $livewire): array {
                        $packageId = $livewire->getOwnerRecord()->getAttribute('external_id');

                        return is_string($packageId) && filled($packageId)
                            ? app(OpenDataPortal::class)->searchResources($packageId, '')
                            : [];
                    }),

                Select::make('type')
                    ->label('Тип ресурсу')
                    ->options(DatasetResourceType::class)
                    ->required()
                    ->rule(Rule::enum(DatasetResourceType::class))
                    ->unique(
                        table: (new DatasetResource)->getTable(),
                        column: 'type',
                        ignoreRecord: true,
                    ),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('external_id')
            ->columns([
                TextColumn::make('type')
                    ->label('Тип ресурсу')
                    ->formatStateUsing(fn (DatasetResourceType $state): string => $state->getLabel()),
                TextColumn::make('external_id')
                    ->label('Зовнішній ідентифікатор')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
