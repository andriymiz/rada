<?php

namespace App\Filament\Resources\DatasetResources\Tables;

use App\Filament\Resources\DatasetResources\Schemas\DatasetResourceInfolist;
use App\Models\Dataset;
use App\Models\DatasetResource;
use App\Services\OpenDataPortal;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class DatasetResourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'dataset',
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Назва')
                    ->searchable(),
                TextColumn::make('format')
                    ->label('Формат'),
                TextColumn::make('size')
                    ->label('Розмір')
                    ->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 1))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Створено')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('updated_at')
                    ->label('Востаннє оновлено')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([
                Action::make('sync')
                    ->label('Оновити')
                    ->icon(Heroicon::ArrowPath)
                    ->action(function (RelationManager $livewire, OpenDataPortal $portal): void {
                        /** @var Dataset $dataset */
                        $dataset = $livewire->getOwnerRecord();
                        $resourceCount = $portal->syncDatasetResources($dataset);

                        Notification::make()
                            ->title('Ресурси набору оновлено')
                            ->body("Синхронізовано ресурсів: {$resourceCount}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->mutateRecordDataUsing(DatasetResourceInfolist::loadResourceDetails(...)),
                Action::make('downloadResource')
                    ->label('Завантажити останню версію')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->iconButton()
                    ->url(function (DatasetResource $record, OpenDataPortal $portal): string {
                        return $portal->getResourceFileUrl($record->dataset->external_id, $record->external_id, $record->name);
                    })
                    ->openUrlInNewTab(),
                Action::make('uploadToArchive')
                    ->label('Завантажити в архів даних')
                    ->icon(Heroicon::OutlinedCircleStack)
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading(fn (DatasetResource $record): string => 'Завантажити '.$record->name.' в архів даних')
                    ->action(function (DatasetResource $record, OpenDataPortal $portal): void {
                        if ($portal->submitResourceToArchive($record)) {
                            Notification::make()
                                ->title('Завантаження в архів даних розпочато')
                                ->body('Статус можна перевірити після завершення обробки.')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Портал не запустив завантаження')
                            ->body('Служба DataPusher не прийняла ресурс до обробки.')
                            ->danger()
                            ->send();
                    }),
                Action::make('openPortalResource')
                    ->label('Відкрити на порталі')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconButton()
                    ->url(function (
                        DatasetResource $record,
                        OpenDataPortal $portal,
                    ): string {
                        return $portal->getResourcePageUrl($record->dataset->external_id, $record->external_id);
                    })
                    ->openUrlInNewTab(),
            ]);
    }
}
