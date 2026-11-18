<?php

namespace App\Filament\Imports;

use App\Models\OrganizationUnit;
use App\Models\Person;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrganizationUnitImporter extends Importer
{
    protected static ?string $model = OrganizationUnit::class;

    /** @var OrganizationUnit */
    protected ?Model $record;

    protected static bool $shouldPreventFormulaInjection = true;

    /** @return array<ImportColumn> */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('uid')->label('Ідентифікатор')->requiredMapping()->rules(['required', 'string', 'max:255']),
            ImportColumn::make('full_name')->label('Назва')->guesses(['name'])->requiredMapping()->rules(['required', 'string', 'max:255']),
            ImportColumn::make('description')->label('Опис')->requiredMapping()->rules(['required', 'string', 'max:300']),
            ImportColumn::make('constituent_doc')->label('Установчий документ')->guesses(['constituentDocUrl'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))->rules(['nullable', 'url:http,https', 'max:2048']),
            ImportColumn::make('homepage')->label('Веб-сторінка')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))->rules(['nullable', 'url:http,https', 'max:2048']),
            ImportColumn::make('headName')->label('Ім’я керівника')->requiredMapping()->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('headPost')->label('Посада керівника')->requiredMapping()->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('organization')->label('Код організації')->guesses(['unitOfId'])
                ->requiredMapping()->rules(['required', 'string', 'size:8'])
                ->relationship(resolveUsing: 'edrpou'),
            ImportColumn::make('parentUnit')->label('Ідентифікатор батьківського підрозділу')->guesses(['subUnitOfId'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))->rules(['nullable', 'string', 'max:255'])
                ->relationship(resolveUsing: fn (mixed $state, array $data): ?OrganizationUnit => OrganizationUnit::query()
                    ->where('uid', $state)
                    ->whereHas('organization', fn (Builder $query): Builder => $query->where('edrpou', $data['organization'] ?? null))
                    ->first()),
            ImportColumn::make('subUnitOfName')->label('Назва батьківського підрозділу')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('contact_point')->label('Контактна особа або підрозділ')->guesses(['contactPointName'])
                ->requiredMapping()->rules(['required', 'string', 'max:255']),
            ImportColumn::make('email')->label('Електронна пошта')->guesses(['contactPointEmail'])
                ->requiredMapping()->castStateUsing(ImportHelper::normalizeEmailCastState(...))
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('phone')->label('Телефон')->guesses(['contactPointTelephone'])
                ->requiredMapping()->castStateUsing(ImportHelper::normalizePhoneCastState(...))
                ->rules(['required', 'string', 'max:255', 'not_regex:/,/']),
            ImportColumn::make('opening_hours')->label('Графік прийому')->guesses(['openingHours'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))->rules(['nullable', 'string', 'max:2048']),
            ImportColumn::make('availability_restriction')->label('Обмеження прийому')->guesses(['availabilityRestriction'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))->rules(['nullable', 'string', 'max:2048']),
        ];
    }

    public function resolveRecord(): OrganizationUnit
    {
        $uid = $this->data['uid'] ?? null;

        return is_string($uid) && filled($uid)
            ? OrganizationUnit::query()->firstOrNew(['uid' => $uid])
            : new OrganizationUnit;
    }

    protected function afterSave(): void
    {
        if (array_key_exists('headName', $this->data) || array_key_exists('headPost', $this->data)) {
            $this->saveHeadRelationship($this->record);
        }
    }

    private function saveHeadRelationship(OrganizationUnit $unit): void
    {
        $headName = $this->data['headName'] ?? null;
        $headPost = $this->data['headPost'] ?? null;

        [$lastName, $firstName, $middleName] = ImportHelper::parseName($headName);

        $official = $unit->organization->officials()
            ->whereHas('person', fn (Builder $query): Builder => $query
                ->where('last_name', $lastName)
                ->where('first_name', $firstName)
                ->where('middle_name', $middleName))
            ->first();

        if ($official === null) {
            $person = Person::query()->firstOrCreate([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_name' => $middleName,
            ]);

            $official = $unit->organization->officials()->create([
                'person_id' => $person->getKey(),
                'position' => $headPost,
            ]);
        }

        $official->position = $headPost;
        $official->save();
        $unit->head()->associate($official);
        $unit->save();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return ImportHelper::getCompletedNotificationBody($import);
    }
}
