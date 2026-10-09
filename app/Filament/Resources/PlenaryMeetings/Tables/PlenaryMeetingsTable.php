<?php

namespace App\Filament\Resources\PlenaryMeetings\Tables;

use App\Models\PlenaryMeeting;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlenaryMeetingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'parliamentarySession.convocation',
            ])->withCount([
                'motions',
                'voteEvents',
            ]))
            ->columns([
                TextColumn::make('meeting_name')
                    ->label('Засідання')
                    ->state(fn (PlenaryMeeting $record): string => $record->displayName()),
                TextColumn::make('date')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('motions_count')
                    ->label('Питань')
                    ->numeric(),
                TextColumn::make('vote_events_count')
                    ->label('Поіменних голосувань')
                    ->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->defaultSort('date', 'desc');
    }
}
