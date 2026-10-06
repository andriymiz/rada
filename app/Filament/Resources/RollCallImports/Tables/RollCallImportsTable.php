<?php

namespace App\Filament\Resources\RollCallImports\Tables;

use App\Enums\RollCallImportStatus;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Jobs\ProcessRollCallImport;
use App\Models\RollCallImport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RollCallImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_filename')
                    ->label('Файл')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('session_label')
                    ->label('Сесія')
                    ->state(fn (RollCallImport $record): string => $record->sessionLabel()),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->date('d.m.Y')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('review')
                    ->label(fn (RollCallImport $record): string => $record->status === RollCallImportStatus::AwaitingReview
                        ? 'Редагувати'
                        : 'Переглянути')
                    ->icon(fn (RollCallImport $record): Heroicon => $record->status === RollCallImportStatus::AwaitingReview
                        ? Heroicon::OutlinedPencilSquare
                        : Heroicon::OutlinedEye)
                    ->tooltip(fn (RollCallImport $record): string => $record->status === RollCallImportStatus::AwaitingReview
                        ? 'Редагувати'
                        : 'Переглянути')
                    ->iconButton()
                    ->url(fn (RollCallImport $record): string => RollCallImportResource::getUrl(
                        $record->status === RollCallImportStatus::AwaitingReview ? 'edit' : 'view',
                        ['record' => $record],
                    ))
                    ->visible(fn (RollCallImport $record): bool => $record->status === RollCallImportStatus::AwaitingReview
                        ? RollCallImportResource::canEdit($record)
                        : RollCallImportResource::canView($record)),
                Action::make('viewError')
                    ->label('Помилка обробки імпорту')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->iconButton()
                    ->color('warning')
                    ->modal()
                    ->modalSubmitAction(fn (Action $action): Action => $action->color('primary'))
                    ->modalSubmitActionLabel('Спробувати ще раз')
                    ->modalCancelActionLabel('Зрозуміло')
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
                Action::make('viewPdf')
                    ->label('Переглянути PDF')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->iconButton()
                    ->url(fn (RollCallImport $record): string => route('roll-call-imports.pdf', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->label('Видалити імпорт')
                    ->icon(Heroicon::OutlinedTrash)
                    ->iconButton(),
            ])
            ->defaultSort('id', 'desc')
            ->poll(fn (): ?string => RollCallImport::query()->pendingOrProcessing()->exists()
                    ? '3s'
                    : null);
    }
}
