<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class RollCallImportProcessed extends Notification
{
    public function __construct(
        private readonly string $filename,
        private readonly bool $successful,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notification = FilamentNotification::make()
            ->title($this->successful ? 'Імпорт завершено' : 'Помилка імпорту')
            ->body($this->successful
                ? "Файл «{$this->filename}» успішно оброблено."
                : "Не вдалося обробити файл «{$this->filename}».");

        return ($this->successful ? $notification->success() : $notification->danger())
            ->getDatabaseMessage();
    }
}
