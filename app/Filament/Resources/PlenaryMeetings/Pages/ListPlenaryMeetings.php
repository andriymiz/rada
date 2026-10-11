<?php

namespace App\Filament\Resources\PlenaryMeetings\Pages;

use App\Actions\QueueRollCallImportsForMeeting;
use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\PlenaryMeetings\Schemas\PlenaryMeetingForm;
use App\Filament\Resources\RollCallImports\Schemas\RollCallImportForm;
use App\Models\PlenaryMeeting;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListPlenaryMeetings extends ListRecords
{
    use HasRadaBreadcrumbs;

    protected static string $resource = PlenaryMeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Створити')
                ->schema([
                    ...PlenaryMeetingForm::components(),
                    RollCallImportForm::fileUpload(),
                ])
                ->using(function (array $data): PlenaryMeeting {
                    return DB::transaction(function () use ($data): PlenaryMeeting {
                        $files = $data['files'] ?? [];
                        $originalFilenames = $data['original_filenames'] ?? [];
                        unset($data['files'], $data['original_filenames']);

                        $meeting = PlenaryMeeting::query()->create($data);

                        if ($files !== []) {
                            $user = auth()->user();

                            abort_unless($user instanceof User, 403);

                            app(QueueRollCallImportsForMeeting::class)->handle(
                                $meeting,
                                $files,
                                $originalFilenames,
                                $user,
                            );

                            Notification::make()
                                ->title('Файли додано до черги обробки')
                                ->success()
                                ->send();
                        }

                        return $meeting;
                    });
                }),
        ];
    }
}
