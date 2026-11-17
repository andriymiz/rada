<?php

namespace App\Filament\Exports;

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
            ExportColumn::make('number')->label('id'),
            ExportColumn::make('voting_identifier')->label('votingIdentifier'),
            ExportColumn::make('person.last_name')->label('familyName'),
            ExportColumn::make('person.first_name')->label('name'),
            ExportColumn::make('person.middle_name')->label('additionalName'),
            ExportColumn::make('person.gender')->label('gender'),
            ExportColumn::make('person.date_of_birth')->label('birthDate'),
            ExportColumn::make('image')->label('image'),
            ExportColumn::make('biography')->label('biography'),
            ExportColumn::make('politicalParty.name')->label('partyName'),
            ExportColumn::make('politicalParty.edrpou')->label('partyId'),
            ExportColumn::make('emails')->label('email'),
            ExportColumn::make('phones')->label('telephone'),
            ExportColumn::make('homepage')->label('homepage'),
            ExportColumn::make('social_accounts')->label('account'),
            ExportColumn::make('faction.name')->label('factionName')->default('Позафракційний'),
            ExportColumn::make('faction_position')->label('factionPost'),
            ExportColumn::make('permanentCommission.name')->label('commissionName'),
            ExportColumn::make('commission_position')->label('commissionPost'),
            ExportColumn::make('area_id')->label('areaId'),
            ExportColumn::make('CATUTTC')->state(fn (): string => 'null'),
            ExportColumn::make('addressAdminUnitL1')->state(fn (): string => 'null'),
            ExportColumn::make('addressAdminUnitL2')->state(fn (): string => 'null'),
            ExportColumn::make('addressAdminUnitL3')->state(fn (): string => 'null'),
            ExportColumn::make('addressAdminUnitL4')->state(fn (): string => 'null'),
            ExportColumn::make('addressPostName')->state(fn (): string => 'null'),
            ExportColumn::make('addressThoroughfare')->state(fn (): string => 'null'),
            ExportColumn::make('addressLocatorDesignator')->state(fn (): string => 'null'),
            ExportColumn::make('addressLocatorName')->state(fn (): string => 'null'),
            ExportColumn::make('addressPostCode')->state(fn (): string => 'null'),
            ExportColumn::make('opening_hours')->label('openingHours'),
            ExportColumn::make('availability_restriction')->label('availabilityRestriction'),
            // ExportColumn::make('start_date')->label('startDate'),
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
