<?php

namespace App\Filament\Resources\RollCallImports\Tables;

use App\Enums\RollCallImportStatus;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Models\RollCallImport;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RollCallImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_filename')
                    ->label('Файл')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('session.convocation.name')
                    ->label('Скликання')
                    ->sortable(),
                TextColumn::make('session.name')
                    ->label('Сесія')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Завантажив')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата завантаження')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn (RollCallImport $record): bool => RollCallImportResource::canView($record)),
            ])
            ->defaultSort('id', 'desc')
            ->poll(fn (): ?string => RollCallImport::query()
                ->whereIn('status', [
                    RollCallImportStatus::Queued->value,
                    RollCallImportStatus::Processing->value,
                ])
                ->exists()
                    ? '3s'
                    : null);
    }
}
