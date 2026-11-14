<?php

namespace App\Filament\Exports;

use App\Enums\DatasetUpdateFrequency;
use App\Models\Dataset;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class DatasetExporter extends Exporter
{
    protected static ?string $model = Dataset::class;

    /**
     * @return array<ExportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('uid')
                ->label('uid')
                ->state(fn (Dataset $record): string => $record->external_id),
            ExportColumn::make('title')
                ->label('title')
                ->state(fn (Dataset $record): string => $record->rec_code?->getLabel() ?? $record->title),
            ExportColumn::make('description')
                ->label('description')
                ->state(fn (Dataset $record): string => $record->meta->description),
            ExportColumn::make('accrualPeriodicity')
                ->label('accrualPeriodicity')
                ->state(fn (Dataset $record): string => self::formatUpdateFrequency(
                    $record->meta->accrual_periodicity,
                )),
            ExportColumn::make('keywords')
                ->label('keywords')
                ->state(fn (Dataset $record): string => implode(', ', $record->meta->keywords)),
            ExportColumn::make('purpose')
                ->label('purpose')
                ->state(fn (Dataset $record): string => $record->meta->purpose),
            ExportColumn::make('url')
                ->label('url')
                ->state(fn (Dataset $record): string => $record->url),
            ExportColumn::make('distributionFormat')
                ->label('distributionFormat')
                ->state(fn (Dataset $record): string => $record->resources
                    ->pluck('format')
                    ->filter(fn (mixed $format): bool => is_string($format) && filled($format))
                    ->unique()
                    ->implode(', ')),
            ExportColumn::make('publisherName')
                ->label('publisherName')
                ->state(fn (Dataset $record): string => $record->publisher->name),
            ExportColumn::make('publisherId')
                ->label('publisherId')
                ->state(fn (Dataset $record): string => $record->publisher->edrpou ?? 'null'),
            ExportColumn::make('contactPointName')
                ->label('contactPointName')
                ->state(fn (Dataset $record): string => $record->meta->contact_point_name),
            ExportColumn::make('contactPointEmail')
                ->label('contactPointEmail')
                ->state(fn (Dataset $record): string => $record->meta->contact_point_email),
        ];
    }

    /**
     * @param  Builder<Dataset>  $query
     * @return Builder<Dataset>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['meta', 'publisher', 'resources']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return ExportHelper::getCompletedNotificationBody($export);
    }

    private static function formatUpdateFrequency(string $value): string
    {
        $frequency = collect(DatasetUpdateFrequency::cases())
            ->first(fn (DatasetUpdateFrequency $case): bool => in_array($value, [
                $case->value,
                $case->getLabel(),
            ], true));

        return $frequency?->getLabel() ?? $value;
    }
}
