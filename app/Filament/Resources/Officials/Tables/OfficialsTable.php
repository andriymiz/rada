<?php

namespace App\Filament\Resources\Officials\Tables;

use App\Models\Person;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OfficialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('№')
                    ->sortable(),
                TextColumn::make('person.short_name')
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
                                ->whereColumn('people.id', 'officials.person_id'), $sortDirection)
                            ->orderBy(Person::query()
                                ->select('first_name')
                                ->whereColumn('people.id', 'officials.person_id'), $sortDirection)
                            ->orderBy(Person::query()
                                ->select('middle_name')
                                ->whereColumn('people.id', 'officials.person_id'), $sortDirection);
                    }),
                TextColumn::make('position')
                    ->label('Посада')
                    ->searchable()
                    ->sortable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['person', 'organization']))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
