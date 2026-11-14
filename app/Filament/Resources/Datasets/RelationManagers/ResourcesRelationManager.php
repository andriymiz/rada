<?php

namespace App\Filament\Resources\Datasets\RelationManagers;

use App\Models\Dataset;
use App\Models\DatasetResource;
use App\Services\OpenDataPortal;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnexpectedValueException;

class ResourcesRelationManager extends RelationManager
{
    protected static string $relationship = 'resources';

    protected static ?string $title = 'Ресурси набору даних';

    protected static ?string $recordTitleAttribute = 'external_id';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return filled($ownerRecord->getAttribute('external_id'))
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('external_id')
            ->columns([
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable(),
                TextColumn::make('external_id')
                    ->label('Ідентифікатор')
                    ->searchable(),
                TextColumn::make('format')
                    ->label('Формат'),
            ])
            ->headerActions([
                Action::make('sync')
                    ->label('Оновити')
                    ->icon(Heroicon::ArrowPath)
                    ->action(function (RelationManager $livewire, OpenDataPortal $portal): void {
                        $dataset = $livewire->getOwnerRecord();

                        if (! $dataset instanceof Dataset) {
                            throw new UnexpectedValueException('Resources can only be synchronized for datasets.');
                        }

                        $resourceCount = $portal->syncDatasetResources($dataset);

                        Notification::make()
                            ->title('Ресурси набору оновлено')
                            ->body("Синхронізовано ресурсів: {$resourceCount}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('openPortalResource')
                    ->label('Відкрити на порталі')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(function (
                        DatasetResource $record,
                        RelationManager $livewire,
                        OpenDataPortal $portal,
                    ): ?string {
                        $packageId = $livewire->getOwnerRecord()->getAttribute('external_id');

                        if (! is_string($packageId) || blank($packageId)) {
                            return null;
                        }

                        return $portal->getResourcePageUrl($packageId, $record->external_id);
                    })
                    ->openUrlInNewTab(),
            ]);
    }
}
