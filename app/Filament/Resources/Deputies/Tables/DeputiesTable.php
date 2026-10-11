<?php

namespace App\Filament\Resources\Deputies\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeputiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Депутат')
                    ->searchable(['family_name', 'first_name', 'additional_name'])
                    ->sortable(['family_name', 'first_name', 'additional_name']),
                TextColumn::make('memberships_count')
                    ->label('Скликань')
                    ->counts('memberships'),
            ])
            ->searchable(false)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
