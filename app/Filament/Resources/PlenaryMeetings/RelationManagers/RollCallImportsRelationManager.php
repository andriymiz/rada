<?php

namespace App\Filament\Resources\PlenaryMeetings\RelationManagers;

use App\Enums\RollCallImportStatus;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Jobs\ProcessRollCallImport;
use App\Models\PlenaryMeeting;
use App\Models\RollCallImport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use LogicException;

class RollCallImportsRelationManager extends RelationManager
{
    protected static string $relationship = 'rollCallImports';

    protected static ?string $title = 'Імпорти';

    public function getOwnerRecord(): PlenaryMeeting
    {
        $ownerRecord = parent::getOwnerRecord();

        if (! $ownerRecord instanceof PlenaryMeeting) {
            throw new LogicException('Roll-call imports must belong to a plenary meeting.');
        }

        return $ownerRecord;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_filename')
                    ->label('Файл')
                    ->limit(40),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Додано')
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label('Перевірити')
                    ->url(fn (RollCallImport $record): string => RollCallImportResource::getUrl('edit', [
                        'record' => $record,
                    ]))
                    ->visible(fn (RollCallImport $record): bool => $record->status === RollCallImportStatus::AwaitingReview),
                Action::make('view')
                    ->label('Переглянути')
                    ->url(fn (RollCallImport $record): string => RollCallImportResource::getUrl('view', [
                        'record' => $record,
                    ]))
                    ->visible(fn (RollCallImport $record): bool => $record->status === RollCallImportStatus::Completed),
                Action::make('viewError')
                    ->label('Помилка імпорту')
                    ->modal()
                    ->modalSubmitActionLabel('Спробувати ще раз')
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Повідомлення')
                            ->state(fn (RollCallImport $record): string => $record->error_message ?? 'Не вдалося визначити причину помилки.'),
                    ])
                    ->action(function (RollCallImport $record): void {
                        $record->update([
                            'status' => RollCallImportStatus::Queued,
                            'error_message' => null,
                            'parsed_result' => null,
                            'processed_at' => null,
                        ]);

                        ProcessRollCallImport::dispatch($record);

                        Notification::make()
                            ->title('Повторну обробку додано до черги')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (RollCallImport $record): bool => $record->status === RollCallImportStatus::Failed),
                DeleteAction::make(),
            ])
            ->defaultSort('id', 'desc')
            ->poll(fn (): ?string => $this->getOwnerRecord()
                ->rollCallImports()
                ->whereIn('status', [
                    RollCallImportStatus::Queued->value,
                    RollCallImportStatus::Processing->value,
                ])
                ->exists() ? '3s' : null);
    }
}
