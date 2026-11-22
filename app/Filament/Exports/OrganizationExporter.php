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
            ExportColumn::make('full_name')->label('name'),
            ExportColumn::make('description')->label('description'),
            ExportColumn::make('constituent_doc')->label('constituentDocUrl'),
            ExportColumn::make('homepage')->label('homepage'),
            ExportColumn::make('social_accounts')->label('socialAccount'),
            ExportColumn::make('logo')->label('logo'),
            ExportColumn::make('head.person.full_name')->label('headName'),
            ExportColumn::make('head.position')->label('headPost'),
            ExportColumn::make('parentOrganization.edrpou')->label('subOrgOfId'),
            ExportColumn::make('parentOrganization.full_name')->label('subOrgOfName'),
            ExportColumn::make('territorialUnit.katottg_code')->label('CATUTTC'),
            ExportColumn::make('territorialUnit.parent.parent.parent.name')->label('addressAdminUnitL1'),
            ExportColumn::make('territorialUnit.parent.parent.name')->label('addressAdminUnitL2'),
            ExportColumn::make('territorialUnit.parent.name')->label('addressAdminUnitL3'),
            ExportColumn::make('territorialUnit.name')->label('addressAdminUnitL4'),
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

            ExportColumn::make('short_name')->label('shortName')->enabledByDefault(false),
            ExportColumn::make('group.name')->label('groupName')->enabledByDefault(false),
            ExportColumn::make('additional_contacts')->label('additionalContacts')->enabledByDefault(false),
        ];
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'head.person',
            'group',
            'parentOrganization',
            'territorialUnit.parent.parent.parent',
        ]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }
}
