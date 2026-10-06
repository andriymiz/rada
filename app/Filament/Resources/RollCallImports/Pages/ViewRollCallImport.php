<?php

namespace App\Filament\Resources\RollCallImports\Pages;

use App\Enums\MotionReviewStatus;
use App\Enums\RollCallImportStatus;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Models\RollCallImport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewRollCallImport extends ViewRecord
{
    protected static string $resource = RollCallImportResource::class;

    protected string $view = 'filament.resources.roll-call-imports.pages.view-roll-call-import';

    public function getTitle(): string
    {
        return "Результати імпорту: {$this->getRecord()->original_filename}";
    }

    public function reviewMotion(int $motionIndex): void
    {
        $wasAlreadyReviewed = false;
        $importCompleted = false;

        DB::transaction(function () use ($motionIndex, &$wasAlreadyReviewed, &$importCompleted): void {
            $record = RollCallImport::query()
                ->lockForUpdate()
                ->findOrFail($this->getRecord()->getKey());

            abort_unless(RollCallImportResource::canView($record), 403);

            if ($record->status !== RollCallImportStatus::AwaitingReview) {
                $wasAlreadyReviewed = true;

                return;
            }

            $parsedResult = $record->parsed_result ?? [];
            $motions = $parsedResult['motions'] ?? [];

            if (! array_key_exists($motionIndex, $motions)) {
                throw ValidationException::withMessages([
                    'motionIndex' => 'Питання не знайдено.',
                ]);
            }

            if (($motions[$motionIndex]['review_status'] ?? null) === MotionReviewStatus::Approved->value) {
                $wasAlreadyReviewed = true;

                return;
            }

            $parsedResult['motions'][$motionIndex]['review_status'] = MotionReviewStatus::Approved->value;

            $allMotionsApproved = $motions !== [] && collect($parsedResult['motions'])
                ->every(fn (array $motion): bool => ($motion['review_status'] ?? null) === MotionReviewStatus::Approved->value);

            $importCompleted = $allMotionsApproved;

            $record->update([
                'parsed_result' => $parsedResult,
                'status' => $allMotionsApproved
                    ? RollCallImportStatus::Completed
                    : RollCallImportStatus::AwaitingReview,
            ]);
        });

        $this->getRecord()->refresh();

        if ($wasAlreadyReviewed) {
            Notification::make()
                ->title('Рішення щодо цього питання вже зафіксоване')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title($importCompleted
                ? 'Усі питання підтверджено. Імпорт завершено.'
                : 'Підтвердження питання збережено')
            ->success()
            ->send();
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
