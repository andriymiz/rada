<?php

namespace App\Filament\Resources\Datasets\Widgets;

use App\Enums\DatasetStatus;
use App\Models\Dataset;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DatasetStatsOverview extends StatsOverviewWidget
{
    // protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $countsByStatus = Dataset::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $countsByStatus->sum();
        $published = (int) $countsByStatus->get(DatasetStatus::Published->value, 0);
        $collecting = (int) $countsByStatus->get(DatasetStatus::Collecting->value, 0);
        $publishedPercentage = (int) round(($published / 75) * 100);
        $otherStatuses = $total - $published - $collecting;

        return [
            Stat::make('Усього', $total)
                ->description('75 обов\'язкових'),
            Stat::make('Опубліковано', $published)
                ->description("{$publishedPercentage}% від усіх наборів")
                ->color('success'),
            Stat::make('В роботі', $collecting)
                ->description("{$otherStatuses} невідомо")
                ->color('warning'),
        ];
    }
}
