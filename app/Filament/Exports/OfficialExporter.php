<?php

namespace App\Filament\Exports;

use App\Models\Employee;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class OfficialExporter extends Exporter
{
    protected static ?string $model = Employee::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('uid'),
            ExportColumn::make('person.full_name')->label('name'),
            ExportColumn::make('person.gender')->label('gender'),
            ExportColumn::make('position')->label('positionName'),
            ExportColumn::make('unitId')->label('unitId')->state(fn (): string => 'null'),
            ExportColumn::make('unitName')->label('unitName')->state(fn (): string => 'null'),
            ExportColumn::make('organization.edrpou')->label('orgId'),
            ExportColumn::make('organization.full_name')->label('orgName'),
            ExportColumn::make('image')->label('img'),
            ExportColumn::make('homepage')->label('homepage'),
            ExportColumn::make('social_accounts')->label('socialAccount'),
            ExportColumn::make('phone')->label('telephone'),
            ExportColumn::make('email')->label('email'),
            ExportColumn::make('opening_hours')->label('openingHours'),
            ExportColumn::make('availability_restriction')->label('availabilityRestriction'),
        ];
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->where('is_post', true)
            ->with(['person', 'organization']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }
}
