<?php

namespace App\Filament\Resources\PlenaryMeetings\Pages;

use App\Actions\QueueRollCallImportsForMeeting;
use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\RollCallImports\Schemas\RollCallImportForm;
use App\Models\CouncilOrganization;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPlenaryMeeting extends EditRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = PlenaryMeetingResource::class;

    public function getTitle(): string
    {
        $meeting = $this->getRecord()->loadMissing('councilSession.convocation');

        return "{$meeting->displayName()} — {$meeting->date->format('d.m.Y')}";
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $organization = CouncilOrganization::query()->orderBy('id')->first();

        if ($organization === null) {
            throw ValidationException::withMessages([
                'data.council_session_id' => 'Спочатку налаштуйте організацію.',
            ]);
        }

        $data['organization_id'] = $organization->id;

        return $data;
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
