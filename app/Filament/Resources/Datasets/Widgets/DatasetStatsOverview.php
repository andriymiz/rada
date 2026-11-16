<?php

namespace App\Filament\Resources\Datasets\Widgets;

use App\Enums\DatasetStatus;
use App\Models\Dataset;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DatasetStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $countsByStatus = Dataset::query()
            ->selectRaw('status, count(*) as total, count(rec_code) as mandatory_total')
            ->groupBy('status')
            ->toBase()
            ->get()
            ->keyBy('status');

        $publishedStats = $countsByStatus->get(DatasetStatus::Published->value);
        $collecting = $countsByStatus->get(DatasetStatus::Collecting->value);
        $unknown = $countsByStatus->get(DatasetStatus::Unknown->value);
        $noInformation = $countsByStatus->get(DatasetStatus::NoInformation->value);
        $notPublished = $countsByStatus->get(DatasetStatus::NotPublished->value);

        // Усього
        $total = (int) $countsByStatus->sum('total');
        $processed = $total - ($unknown->total ?? 0);
        $processedPercentage = $total > 0 ? (int) round(($processed / $total) * 100) : 0;

        // Обов'язкових
        $mandatory = (int) ($publishedStats->mandatory_total ?? 0);
        $mandatoryTotal = (int) $countsByStatus->sum('mandatory_total');
        $mandatoryPercentage = $mandatoryTotal > 0
            ? (int) round(($mandatory / $mandatoryTotal) * 100)
            : 0;

        // В роботі
        $inProgress = ($unknown->total ?? 0) + ($collecting->total ?? 0);
        $inProgressTotal = $total - ($notPublished->total ?? 0) - ($noInformation->total ?? 0);
        $inProgressPercentage = $inProgressTotal > 0 ? (int) round(($inProgress / $inProgressTotal) * 100) : 0;

        return [
            Stat::make('Усього', "{$processed} / {$total}")
                ->description("{$processedPercentage}% опрацьовано")
                ->color('success'),
            Stat::make('Обов\'язкових', "{$mandatory} / {$mandatoryTotal}")
                ->description("{$mandatoryPercentage}% згідно постанови КМУ №835")
                ->color('warning'),
            Stat::make('В роботі', "{$inProgress} / {$inProgressTotal}")
                ->description("{$inProgressPercentage}% без інформації")
                ->color('info'),
        ];
    }
}
