<?php

namespace App\Filament\Resources\Convocations\Tables;

use App\Filament\Resources\Convocations\Resources\Deputies\DeputyResource;
use App\Filament\Resources\Convocations\Schemas\ConvocationForm;
use App\Models\Convocation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConvocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('№')
                    ->sortable(),
                TextColumn::make('label')
                    ->label('Назва')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date_range')
                    ->label('Дата')
                    ->state(fn (Convocation $record): string => $record->date_from === null && $record->date_until === null
                        ? '—'
                        : ($record->date_from?->format('d.m.Y') ?? '—')
                            .' - '.
                            ($record->date_until?->format('d.m.Y') ?? '—')),
                TextColumn::make('sessions_count')
                    ->label('Сесій')
                    ->counts('sessions')
                    ->numeric(),
            ])
            ->defaultSort('id', 'desc')
            ->searchable(false)
            ->recordActions([
                Action::make('deputies')
                    ->label('Депутати')
                    ->icon(Heroicon::OutlinedUsers)
                    ->iconButton()
                    ->url(fn (Convocation $record): string => DeputyResource::getUrl(
                        parameters: ['convocation' => $record],
                    )),
                EditAction::make()
                    ->schema(ConvocationForm::components()),
            ]);
    }
}
