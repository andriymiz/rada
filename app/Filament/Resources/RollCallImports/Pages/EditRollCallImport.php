<?php

namespace App\Filament\Resources\RollCallImports\Pages;

use App\Enums\MotionReviewStatus;
use App\Enums\RollCallImportStatus;
use App\Filament\Concerns\HasRadaBreadcrumbs;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Models\PlenaryMeeting;
use App\Models\RollCallImport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditRollCallImport extends EditRecord
{
    use HasRadaBreadcrumbs;

    protected static string $resource = RollCallImportResource::class;

    protected string $view = 'filament.resources.roll-call-imports.pages.edit-roll-call-import';

    public function getTitle(): string
    {
        return "Підтвердження імпорту: {$this->getRecord()->original_filename}";
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['plenary_meeting_id'] = $this->getRecord()->plenary_meeting_id;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = RollCallImport::query()
            ->lockForUpdate()
            ->findOrFail($this->getRecord()->getKey());

        abort_unless(RollCallImportResource::canEdit($record), 403);

        if (! self::hasAllMotionsApproved($record->parsed_result ?? [])) {
            throw ValidationException::withMessages([
                'data.plenary_meeting_id' => 'Підтвердіть усі питання перед збереженням імпорту.',
            ]);
        }

        $meeting = PlenaryMeeting::query()->find($data['plenary_meeting_id'] ?? null);

        if ($meeting === null) {
            throw ValidationException::withMessages([
                'data.plenary_meeting_id' => 'Оберіть засідання зі списку або створіть нове.',
            ]);
        }

        $data['session_id'] = $meeting->parliamentary_session_id;
        $data['status'] = RollCallImportStatus::Completed;

        return $data;
    }

    public function reviewMotion(int $motionIndex): void
    {
        $wasAlreadyReviewed = false;

        DB::transaction(function () use ($motionIndex, &$wasAlreadyReviewed): void {
            $record = RollCallImport::query()
                ->lockForUpdate()
                ->findOrFail($this->getRecord()->getKey());

            abort_unless(RollCallImportResource::canEdit($record), 403);

            $parsedResult = $record->parsed_result ?? [];
            $motions = $parsedResult['motions'] ?? [];

            if (! is_array($motions) || ! array_key_exists($motionIndex, $motions)) {
                throw ValidationException::withMessages([
                    'motionIndex' => 'Питання не знайдено.',
                ]);
            }

            if (($motions[$motionIndex]['review_status'] ?? null) === MotionReviewStatus::Approved->value) {
                $wasAlreadyReviewed = true;

                return;
            }

            $parsedResult['motions'][$motionIndex]['review_status'] = MotionReviewStatus::Approved->value;

            $record->update(['parsed_result' => $parsedResult]);
        });

        $this->getRecord()->refresh();

        Notification::make()
            ->title($wasAlreadyReviewed
                ? 'Це питання вже підтверджено'
                : 'Підтвердження питання збережено')
            ->color($wasAlreadyReviewed ? 'warning' : 'success')
            ->send();
    }

    public function areAllMotionsApproved(): bool
    {
        return self::hasAllMotionsApproved($this->getRecord()->parsed_result ?? []);
    }

    public function canFinalizeImport(): bool
    {
        return $this->areAllMotionsApproved()
            && filled($this->data['plenary_meeting_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $parsedResult
     */
    private static function hasAllMotionsApproved(array $parsedResult): bool
    {
        $motions = $parsedResult['motions'] ?? [];

        return is_array($motions)
            && $motions !== []
            && collect($motions)->every(
                static fn (mixed $motion): bool => is_array($motion)
                    && ($motion['review_status'] ?? null) === MotionReviewStatus::Approved->value,
            );
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Імпорт збережено та завершено';
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
