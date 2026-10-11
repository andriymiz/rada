<?php

namespace App\Filament\Resources\PlenaryMeetings\Tables;

use App\Enums\PlenaryMeetingStatus;
use App\Enums\RollCallImportStatus;
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
                'rollCallImports as imports_awaiting_review_count' => fn (Builder $query): Builder => $query
                    ->where('status', RollCallImportStatus::AwaitingReview->value),
                'rollCallImports as imports_processing_count' => fn (Builder $query): Builder => $query
                    ->whereIn('status', [
                        RollCallImportStatus::Queued->value,
                        RollCallImportStatus::Processing->value,
                    ]),
                'rollCallImports as imports_failed_count' => fn (Builder $query): Builder => $query
                    ->where('status', RollCallImportStatus::Failed->value),
            ]))
            ->columns([
                TextColumn::make('meeting_name')
                    ->label('Засідання')
                    ->state(fn (PlenaryMeeting $record): string => $record->displayName()),
                TextColumn::make('date')
                    ->label('Дата')
                    ->date()
                    ->sortable(),
                TextColumn::make('motions_count')
                    ->label('Питань')
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->state(fn (PlenaryMeeting $record): PlenaryMeetingStatus => PlenaryMeetingStatus::fromMeeting($record))
                    ->color(fn (PlenaryMeetingStatus $state): string => $state->getColor()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Відкрити'),
            ])
            ->defaultSort('date', 'desc');
    }
}
