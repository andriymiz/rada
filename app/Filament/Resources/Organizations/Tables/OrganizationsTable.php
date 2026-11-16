<?php

namespace App\Filament\Resources\Organizations\Tables;

use App\Models\Organization;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('edrpou')
                    ->label('ЄДРПОУ')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable()
                    ->state(fn (Organization $record): string => $record->short_name ?? $record->name)
                    ->wrap()
                    ->sortable(),
                TextColumn::make('head.person.short_name')
                    ->label('Керівник'),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['head.person']))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
