<?php

namespace App\Filament\Resources\RollCallImports\Pages;

use App\Enums\MotionResult;
use App\Enums\MotionReviewStatus;
use App\Enums\RollCallImportStatus;
use App\Enums\VoteOption;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
use App\Filament\Resources\RollCallImports\RollCallImportResource;
use App\Models\Deputy;
use App\Models\PlenaryMeeting;
use App\Models\RollCallImport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** @extends EditRecord<RollCallImport> */
class EditRollCallImport extends EditRecord
{
    protected static string $resource = RollCallImportResource::class;

    protected string $view = 'filament.resources.roll-call-imports.pages.edit-roll-call-import';

    public function getTitle(): string
    {
        return "Підтвердження імпорту: {$this->getRecord()->original_filename}";
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            PlenaryMeetingResource::getUrl() => 'Засідання',
            '' => 'Імпорти',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = RollCallImport::query()
            ->lockForUpdate()
            ->findOrFail($this->getRecord()->id);

        abort_unless(RollCallImportResource::canEdit($record), 403);

        if (! self::hasAllMotionsApproved($record->parsed_result ?? [])) {
            throw ValidationException::withMessages([
                'data' => 'Підтвердіть усі питання перед завершенням імпорту.',
            ]);
        }

        if ($record->plenary_meeting_id === null) {
            throw ValidationException::withMessages([
                'data' => 'Цей імпорт не прив’язаний до засідання. Створіть імпорт зі сторінки засідання.',
            ]);
        }

        $data['status'] = RollCallImportStatus::Completed;

        return $data;
    }

    public function reviewMotion(int $motionIndex): void
    {
        $wasAlreadyReviewed = false;

        DB::transaction(function () use ($motionIndex, &$wasAlreadyReviewed): void {
            $record = RollCallImport::query()
                ->lockForUpdate()
                ->findOrFail($this->getRecord()->id);

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

            $meeting = PlenaryMeeting::query()->find($record->plenary_meeting_id);

            if ($meeting === null) {
                throw ValidationException::withMessages([
                    'data' => 'Цей імпорт не прив’язаний до засідання.',
                ]);
            }

            $parsedMotion = $motions[$motionIndex];
            $questionNumber = filter_var($parsedMotion['question_number'] ?? null, FILTER_VALIDATE_INT);
            $title = $parsedMotion['title'] ?? null;
            $result = self::motionResult($parsedMotion['result'] ?? null);
            $votes = $parsedMotion['votes'] ?? null;

            if ($questionNumber === false || ! is_string($title) || blank($title) || $result === null || ! is_array($votes)) {
                throw ValidationException::withMessages([
                    'data' => 'Не вдалося перевірити дані питання з імпорту.',
                ]);
            }

            if ($meeting->motions()->where('number', $questionNumber)->exists()) {
                throw ValidationException::withMessages([
                    'data' => "Питання № {$questionNumber} вже існує в цьому засіданні.",
                ]);
            }

            $normalizedVotes = collect($votes)->map(function (mixed $vote): array {
                if (! is_array($vote) || ! is_string($vote['name'] ?? null) || ! is_string($vote['result'] ?? null)) {
                    throw ValidationException::withMessages([
                        'data' => 'Не вдалося перевірити поіменні голоси в імпорті.',
                    ]);
                }

                $option = self::voteOption($vote['result']);

                if ($option === null) {
                    throw ValidationException::withMessages([
                        'data' => "Невідомий варіант голосу: {$vote['result']}.",
                    ]);
                }

                return [
                    'name' => $vote['name'],
                    'option' => $option,
                ];
            });

            $convocationId = $meeting->councilSession->convocation_id;
            $deputiesByName = Deputy::query()
                ->whereHas('memberships', fn ($query) => $query->where('convocation_id', $convocationId))
                ->get(['id', 'family_name', 'first_name', 'additional_name'])
                ->toBase()
                ->groupBy('full_name')
                ->only($normalizedVotes->pluck('name')->unique()->all());

            $motion = $meeting->motions()->create([
                'uid' => "{$meeting->date->toDateString()}-{$meeting->getKey()}-{$questionNumber}",
                'roll_call_import_id' => $record->getKey(),
                'number' => $questionNumber,
                'title' => $title,
                'project_number' => $parsedMotion['project_number'] ?? null,
                'result' => $result,
                'text_url' => null,
                'text' => $record->original_filename,
            ]);

            $voteEvent = $motion->voteEvents()->create([
                'result' => $result->getLabel(),
            ]);

            foreach ($normalizedVotes as $vote) {
                $matchingDeputies = $deputiesByName->get($vote['name'], collect());
                $deputy = $matchingDeputies->count() === 1 ? $matchingDeputies->first() : null;

                $voteEvent->votes()->create([
                    'deputy_id' => $deputy?->getKey(),
                    'voter_name' => $vote['name'],
                    'option' => $vote['option'],
                ]);
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
        return $this->areAllMotionsApproved();
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
        return 'Імпорт завершено';
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

    protected function getRedirectUrl(): string
    {
        $meeting = $this->getRecord()->plenaryMeeting;

        return $meeting === null
            ? PlenaryMeetingResource::getUrl()
            : PlenaryMeetingResource::getUrl('edit', ['record' => $meeting]);
    }

    private static function motionResult(mixed $label): ?MotionResult
    {
        if (! is_string($label)) {
            return null;
        }

        foreach (MotionResult::cases() as $result) {
            if ($result->getLabel() === $label) {
                return $result;
            }
        }

        return null;
    }

    private static function voteOption(string $label): ?VoteOption
    {
        foreach (VoteOption::cases() as $option) {
            if ($option->getLabel() === $label) {
                return $option;
            }
        }

        return null;
    }
}
