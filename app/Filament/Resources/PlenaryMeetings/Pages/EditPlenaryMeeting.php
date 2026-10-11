<?php

namespace App\Filament\Resources\PlenaryMeetings\Pages;

use App\Actions\QueueRollCallImportsForMeeting;
use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\RollCallImports\Schemas\RollCallImportForm;
use App\Models\PlenaryMeeting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** @extends EditRecord<PlenaryMeeting> */
class EditPlenaryMeeting extends EditRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = PlenaryMeetingResource::class;

    public function getTitle(): string
    {
        $meeting = $this->getRecord()->loadMissing('councilSession.convocation');

        return "{$meeting->displayName()} — {$meeting->date->format('d.m.Y')}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadRollCallFiles')
                ->label('Імпортувати поіменні голосування')
                ->schema([RollCallImportForm::fileUpload(required: true)])
                ->action(function (array $data): void {
                    $user = auth()->user();

                    abort_unless($user instanceof User, 403);

                    app(QueueRollCallImportsForMeeting::class)->handle(
                        $this->getRecord(),
                        $data['files'],
                        $data['original_filenames'] ?? [],
                        $user,
                    );

                    Notification::make()
                        ->title('Файли додано до черги обробки')
                        ->success()
                        ->send();
                }),
        ];
    }
}
