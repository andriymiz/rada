<?php

namespace App\Filament\Exports;

use App\Models\OrganizationUnit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class OrganizationUnitExporter extends Exporter
{
    protected static ?string $model = OrganizationUnit::class;

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uid')->label('uid'),
            ExportColumn::make('full_name')->label('name'),
            ExportColumn::make('description')->label('description'),
            ExportColumn::make('constituent_doc')->label('constituentDocUrl'),
            ExportColumn::make('homepage')->label('homepage'),
            ExportColumn::make('head.person.full_name')->label('headName'),
            ExportColumn::make('head.position')->label('headPost'),
            ExportColumn::make('organization.edrpou')->label('unitOfId'),
            ExportColumn::make('organization.name')->label('unitOfName'),
            ExportColumn::make('parentUnit.uid')->label('subUnitOfId'),
            ExportColumn::make('parentUnit.full_name')->label('subUnitOfName'),
            ExportColumn::make('CATUTTC')->label('CATUTTC')->state(fn (): string => 'null'),
            ExportColumn::make('addressAdminUnitL1')->label('addressAdminUnitL1')->state(fn (): string => 'null'),
            ExportColumn::make('addressAdminUnitL2')->label('addressAdminUnitL2')->state(fn (): string => 'null'),
            ExportColumn::make('addressAdminUnitL3')->label('addressAdminUnitL3')->state(fn (): string => 'null'),
            ExportColumn::make('addressAdminUnitL4')->label('addressAdminUnitL4')->state(fn (): string => 'null'),
            ExportColumn::make('addressPostName')->label('addressPostName')->state(fn (): string => 'null'),
            ExportColumn::make('addressThoroughfare')->label('addressThoroughfare')->state(fn (): string => 'null'),
            ExportColumn::make('addressLocatorDesignator')->label('addressLocatorDesignator')->state(fn (): string => 'null'),
            ExportColumn::make('addressLocatorName')->label('addressLocatorName')->state(fn (): string => 'null'),
            ExportColumn::make('addressPostCode')->label('addressPostCode')->state(fn (): string => 'null'),
            ExportColumn::make('contact_point')->label('contactPointName'),
            ExportColumn::make('email')->label('contactPointEmail'),
            ExportColumn::make('phone')->label('contactPointTelephone'),
            ExportColumn::make('opening_hours')->label('openingHours'),
            ExportColumn::make('availability_restriction')->label('availabilityRestriction'),
        ];
    }

    /** @param Builder<OrganizationUnit> $query
     * @return Builder<OrganizationUnit>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['organization', 'head.person', 'parentUnit']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }
}
