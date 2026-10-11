<?php

namespace App\Filament\Resources\Deputies\RelationManagers;

use App\Enums\ContactPointType;
use App\Rules\ContactPointValue;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactPointsRelationManager extends RelationManager
{
    protected static string $relationship = 'contactPoints';

    protected static ?string $title = 'Контактні дані';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Тип контакту')
                    ->options(ContactPointType::class)
                    ->required()
                    ->live()
                    ->searchable()
                    ->native(false),
                TextInput::make('value')
                    ->label('Значення')
                    ->required()
                    ->maxLength(2048)
                    ->rule(new ContactPointValue),
                Toggle::make('is_public')
                    ->label('Публічний')
                    ->default(false),
                Textarea::make('note')
                    ->label('Примітка')
                    ->maxLength(2048)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('value')
            ->columns([
                TextColumn::make('type')
                    ->label('Тип'),
                TextColumn::make('value')
                    ->label('Значення')
                    ->searchable()
                    ->wrap(),
                IconColumn::make('is_public')
                    ->label('Публічний')
                    ->boolean(),
                TextColumn::make('note')
                    ->label('Примітка')
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Додати контакт'),
            ])
            ->searchable(false)
            ->paginated(false)
            ->recordActions([
                EditAction::make()
                    ->label('Редагувати'),
            ]);
    }
}
