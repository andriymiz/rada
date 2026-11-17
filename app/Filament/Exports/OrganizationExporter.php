<?php

namespace App\Filament\Exports;

use App\Models\Organization;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class OrganizationExporter extends Exporter
{
    protected static ?string $model = Organization::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('edrpou')->label('Id'),
            ExportColumn::make('name'),
            ExportColumn::make('description'),
            ExportColumn::make('constituent_doc')->label('constituentDocUrl'),
            ExportColumn::make('homepage'),
            ExportColumn::make('social_accounts')->label('socialAccount'),
            ExportColumn::make('logo'),
            ExportColumn::make('head.person.full_name')->label('headName'),
            ExportColumn::make('head.position')->label('headPost'),
            ExportColumn::make('parentOrganization.edrpou')->label('subOrgOfId'),
            ExportColumn::make('parentOrganization.name')->label('subOrgOfName'),
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
            ExportColumn::make('contact_point')->label('contactPointName'),
            ExportColumn::make('emails')->label('contactPointEmail'),
            ExportColumn::make('phones')->label('contactPointTelephone'),
            ExportColumn::make('opening_hours')->label('openingHours'),
            ExportColumn::make('availability_restriction')->label('availabilityRestriction'),
        ];
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['head.person', 'parentOrganization']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }
}
