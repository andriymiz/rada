<?php

namespace App\Filament\Exports;

use App\Enums\ContactPointType;
use App\Enums\Gender;
use App\Models\ContactPoint;
use App\Models\Official;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OfficialExporter extends Exporter
{
    protected static ?string $model = Official::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('name')
                ->state(fn (Official $record): string => $record->person->full_name)
                ->preventFormulaInjection(),
            ExportColumn::make('gender')
                ->label('gender')
                ->state(fn (Official $record): string => self::getGenderValue($record))
                ->preventFormulaInjection(),
            ExportColumn::make('positionName')
                ->label('positionName')
                ->state(fn (Official $record): string => $record->position)
                ->preventFormulaInjection(),
            ExportColumn::make('orgId')
                ->label('orgId')
                ->state(fn (Official $record): string => $record->organization->edrpou ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('orgName')
                ->label('orgName')
                ->state(fn (Official $record): string => $record->organization->name)
                ->preventFormulaInjection(),
            ExportColumn::make('img')
                ->label('img')
                ->state(fn (Official $record): string => $record->image ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('homepage')
                ->label('homepage')
                ->state(fn (Official $record): string => $record->homepage ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('socialAccount')
                ->label('socialAccount')
                ->state(fn (Official $record): string => self::getContactPointValues($record))
                ->preventFormulaInjection(),
            ExportColumn::make('telephone')
                ->label('telephone')
                ->state(fn (Official $record): string => self::getContactPointValues($record, ContactPointType::Phone))
                ->preventFormulaInjection(),
            ExportColumn::make('email')
                ->label('email')
                ->state(fn (Official $record): string => self::getContactPointValues($record, ContactPointType::Email))
                ->preventFormulaInjection(),
            ExportColumn::make('unitId')
                ->label('unitId')
                ->state(fn (): string => 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('unitName')
                ->label('unitName')
                ->state(fn (): string => 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('openingHours')
                ->label('openingHours')
                ->state(fn (Official $record): string => $record->reception->opening_hours ?? 'null')
                ->preventFormulaInjection(),
            ExportColumn::make('availabilityRestriction')
                ->label('availabilityRestriction')
                ->state(fn (Official $record): string => $record->reception->availability_restriction ?? 'null')
                ->preventFormulaInjection(),
        ];
    }

    /**
     * @param  Builder<Official>  $query
     * @return Builder<Official>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'person',
            'organization',
            'reception',
            'contactPoints' => fn (MorphMany $contactPoints): MorphMany => $contactPoints
                ->where('is_public', true)
                ->orderBy('id'),
        ]);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }

    private static function getGenderValue(Official $record): string
    {
        $gender = $record->person->getAttribute('gender');

        return (string) ($gender instanceof Gender ? $gender->value : Gender::Unknown->value);
    }

    private static function getContactPointValues(Official $record, ?ContactPointType $type = null): string
    {
        $values = $record->contactPoints
            ->filter(fn (ContactPoint $contactPoint): bool => $contactPoint->is_public
                && ($type === null
                    ? ! in_array($contactPoint->type, [ContactPointType::Email, ContactPointType::Phone], true)
                    : $contactPoint->type === $type))
            ->pluck('value')
            ->filter()
            ->unique()
            ->implode(', ');

        return $values !== '' ? $values : 'null';
    }
}
