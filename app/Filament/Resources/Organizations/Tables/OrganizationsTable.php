<?php

namespace App\Filament\Resources\Organizations\Tables;

use App\Models\Organization;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
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
                TextColumn::make('full_name')
                    ->label('Назва')
                    ->searchable()
                    ->state(fn (Organization $record): string => $record->short_name ?? $record->full_name)
                    ->wrap()
                    ->sortable(),
                TextColumn::make('head.person.short_name')
                    ->label('Керівник'),
            ])
            ->defaultGroup(
                Group::make('group_id')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (Organization $record): string => $record->group->name ?? 'Інше')
                    ->collapsible(),
            )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['head.person', 'group']))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
