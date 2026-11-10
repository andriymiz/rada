<?php

namespace App\Filament\Resources\OpenData\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OpenDataTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Код ресурсу')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('package_id')
                    ->label('Ідентифікатор набору')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('resource_id')
                    ->label('Ідентифікатор ресурсу')
                    ->searchable()
                    ->copyable(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
