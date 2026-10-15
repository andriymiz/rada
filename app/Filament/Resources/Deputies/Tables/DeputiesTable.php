<?php

namespace App\Filament\Resources\Deputies\Tables;

use App\Enums\DeputyStatus;
use App\Models\Person;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeputiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('short_name')
                    ->label('ПІБ')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('person', fn (Builder $personQuery): Builder => $personQuery
                            ->where('last_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('middle_name', 'like', "%{$search}%")))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $sortDirection = match ($direction) {
                            'asc' => 'asc',
                            'desc' => 'desc',
                            default => throw new \InvalidArgumentException("Unsupported sort direction [{$direction}]."),
                        };

                        return $query
                            ->orderBy(Person::query()
                                ->select('last_name')
                                ->whereColumn('people.id', 'deputies.person_id'), $sortDirection)
                            ->orderBy(Person::query()
                                ->select('first_name')
                                ->whereColumn('people.id', 'deputies.person_id'), $sortDirection)
                            ->orderBy(Person::query()
                                ->select('middle_name')
                                ->whereColumn('people.id', 'deputies.person_id'), $sortDirection);
                    }),
                TextColumn::make('politicalParty.name')
                    ->label('Партія')
                    ->placeholder('—'),
                TextColumn::make('voting_identifier')
                    ->label('Номер пульта')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (DeputyStatus $state): string => $state->getColor())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(DeputyStatus::class),
            ])
            ->searchable(true)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('person'))
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
