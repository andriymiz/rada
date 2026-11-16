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
            ExportColumn::make('Id')
                ->state(fn (Organization $record): string => $record->edrpou)
                ->preventFormulaInjection(),
            ExportColumn::make('name')
                ->preventFormulaInjection(),
            ExportColumn::make('shortName')
                ->state(fn (Organization $record): string => $record->short_name)
                ->preventFormulaInjection(),
            ExportColumn::make('description')
                ->state(fn (Organization $record): string => $record->description ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('constituentDocUrl')
                ->state(fn (Organization $record): string => $record->constituent_doc ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('homepage')
                ->state(fn (Organization $record): string => $record->homepage ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('socialAccount')
                ->label('socialAccount')
                ->state(fn (Organization $record): string => $record->social_accounts === null
                    ? 'null'
                    : implode(', ', $record->social_accounts))
                ->preventFormulaInjection(),
            ExportColumn::make('contactPointName')
                ->label('contactPointName')
                ->state(fn (Organization $record): string => $record->contact_point ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('contactPointEmail')
                ->label('contactPointEmail')
                ->state(fn (Organization $record): string => $record->email ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('contactPointTelephone')
                ->label('contactPointTelephone')
                ->state(fn (Organization $record): string => $record->phone ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('openingHours')
                ->label('openingHours')
                ->state(fn (Organization $record): string => $record->opening_hours ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('availabilityRestriction')
                ->label('availabilityRestriction')
                ->state(fn (Organization $record): string => $record->availability_restriction ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('logo')
                ->state(fn (Organization $record): string => $record->logo ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('headName')
                ->state(fn (Organization $record): string => $record->head === null
                    ? 'null'
                    : $record->head->person->full_name)
                ->preventFormulaInjection(),
            ExportColumn::make('headPost')
                ->state(fn (Organization $record): string => $record->head === null
                    ? 'null'
                    : $record->head->position)
                ->preventFormulaInjection(),
            ExportColumn::make('subOrgOfId')
                ->state(fn (Organization $record): string => $record->parentOrganization === null
                    ? 'null'
                    : $record->parentOrganization->edrpou)
                ->preventFormulaInjection(),
            ExportColumn::make('subOrgOfName')
                ->state(fn (Organization $record): string => $record->parentOrganization === null
                    ? 'null'
                    : $record->parentOrganization->name)
                ->preventFormulaInjection(),
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
