<?php

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Exports\OrganizationExporter;
use App\Filament\Imports\OrganizationImporter;
use App\Filament\Resources\Organizations\OrganizationResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrganizations extends ListRecords
{
    use HasRadaBreadcrumbs;

    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ImportAction::make()
                    ->label('Імпорт з CSV')
                    ->pluralModelLabel('організацій')
                    ->importer(OrganizationImporter::class),
                ExportAction::make()
                    ->label('Експорт у CSV')
                    ->pluralModelLabel('організацій')
                    ->exporter(OrganizationExporter::class)
                    ->fileName('organizations')
                    ->columnMapping(false)
                    ->formats([ExportFormat::Csv])
                    ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                        'head.person',
                        'parentOrganization',
                    ])),
            ])
                ->label('Функції')
                ->color('gray')
                ->button(),
            CreateAction::make(),
        ];
    }
}
