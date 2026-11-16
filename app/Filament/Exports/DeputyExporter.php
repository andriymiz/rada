<?php

namespace App\Filament\Exports;

use App\Enums\Gender;
use App\Models\Deputy;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class DeputyExporter extends Exporter
{
    protected static ?string $model = Deputy::class;

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('number')
                ->label('number')
                ->state(fn (Deputy $record): string => (string) ($record->number ?? 'null'))
                ->preventFormulaInjection(),
            ExportColumn::make('votingIdentifier')
                ->label('votingIdentifier')
                ->state(fn (Deputy $record): string => $record->voting_identifier ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('familyName')
                ->label('familyName')
                ->state(fn (Deputy $record): string => $record->person->last_name ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('name')
                ->label('name')
                ->state(fn (Deputy $record): string => $record->person->first_name ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('additionalName')
                ->label('additionalName')
                ->state(fn (Deputy $record): string => $record->person->middle_name ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('gender')
                ->label('gender')
                ->state(fn (Deputy $record): string => (string) ($record->person->gender ?? Gender::Unknown)->value)
                ->preventFormulaInjection(),
            ExportColumn::make('birthDate')
                ->label('birthDate')
                ->state(fn (Deputy $record): string => $record->person->date_of_birth?->toDateString() ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('image')
                ->label('image')
                ->state(fn (Deputy $record): string => $record->image ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('biography')
                ->label('biography')
                ->state(fn (Deputy $record): string => $record->biography ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('partyName')
                ->label('partyName')
                ->state(fn (Deputy $record): string => $record->politicalParty->name ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('partyId')
                ->label('partyId')
                ->state(fn (Deputy $record): string => $record->politicalParty->edrpou ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('email')
                ->label('email')
                ->state(fn (Deputy $record): string => $record->email ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('telephone')
                ->label('telephone')
                ->state(fn (Deputy $record): string => $record->phone ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('homepage')
                ->label('homepage')
                ->state(fn (Deputy $record): string => $record->homepage ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('account')
                ->label('account')
                ->state(fn (Deputy $record): string => $record->social_accounts ? implode(', ', $record->social_accounts) : 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('factionName')
                ->label('factionName')
                ->state(fn (Deputy $record): string => $record->faction->name ?? 'Позафракційний')
                ->preventFormulaInjection(),
            ExportColumn::make('factionPost')
                ->label('factionPost')
                ->state(fn (Deputy $record): string => $record->faction_position ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('startDate')
                ->label('startDate')
                ->state(fn (Deputy $record): string => $record->start_date?->toDateString() ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('commissionName')
                ->label('commissionName')
                ->state(fn (Deputy $record): string => $record->permanentCommission->name ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('commissionPost')
                ->label('commissionPost')
                ->state(fn (Deputy $record): string => $record->commission_position ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('areaId')
                ->label('areaId')
                ->state(fn (Deputy $record): string => (string) ($record->area_id ?? 'null'))
                ->preventFormulaInjection(),
            ExportColumn::make('openingHours')
                ->label('openingHours')
                ->state(fn (Deputy $record): string => $record->opening_hours ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('availabilityRestriction')
                ->label('availabilityRestriction')
                ->state(fn (Deputy $record): string => $record->availability_restriction ?? 'null')
                ->preventFormulaInjection(),
        ];
    }

    /**
     * @param  Builder<Deputy>  $query
     * @return Builder<Deputy>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['person', 'politicalParty', 'faction', 'permanentCommission']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }
}
