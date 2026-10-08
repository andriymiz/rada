<?php

namespace App\Filament\Resources\RollCallImports\Pages;

use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewRollCallImport extends ViewRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = RollCallImportResource::class;

    protected string $view = 'filament.resources.roll-call-imports.pages.view-roll-call-import';

    public function getTitle(): string
    {
        return "Результати імпорту: {$this->getRecord()->original_filename}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPdf')
                ->label('Переглянути PDF')
                ->url(fn (): string => route('roll-call-imports.pdf', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
