<?php

namespace App\Filament\Resources\TerritorialUnits\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Imports\TerritorialUnitImporter;
use App\Filament\Resources\TerritorialUnits\TerritorialUnitResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTerritorialUnits extends ListRecords
{
    use HasRadaBreadcrumbs;

    protected static string $resource = TerritorialUnitResource::class;

    public const KATOTTG_URL = 'https://mininfra.gov.ua/diialnist/rozvytok-mistsevoho-samovriaduvannia/kodyfikator-administratyvno-terytorialnykh-odynyts-ta-terytorii-terytorialnykh-hromad';

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('helpPublicationRecommendations')
                    ->label('Про Кодифікатор')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(self::KATOTTG_URL)
                    ->openUrlInNewTab(),
            ])
                ->label('Довідка')
                ->icon(Heroicon::OutlinedQuestionMarkCircle)
                ->color('gray')
                ->button(),
            ActionGroup::make([
                ImportAction::make()
                    ->pluralModelLabel('територіальних одиниць')
                    ->importer(TerritorialUnitImporter::class)
                    ->chunkSize(100_000),
            ])
                ->label('Функції')
                ->color('gray')
                ->button(),
        ];
    }
}
