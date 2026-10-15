<?php

namespace App\Filament\Resources\Convocations\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermanentCommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permanentCommissions';

    protected static ?string $title = 'Постійні комісії';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Назва')
                    ->sortable()
                    ->searchable(),
            ])
            ->searchable(false)
            ->headerActions([
                CreateAction::make()
                    ->label('Додати комісію')
                    ->schema([
                        TextInput::make('name')
                            ->label('Назва')
                            ->required()
                            ->maxLength(255),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Редагувати')
                    ->schema([
                        TextInput::make('name')
                            ->label('Назва')
                            ->required()
                            ->maxLength(255),
                    ]),
                DeleteAction::make()
                    ->authorizationNotification(),
            ]);
    }
}
