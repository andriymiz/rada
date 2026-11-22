<?php

namespace App\Filament\Imports;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\OrganizationGroup;
use App\Models\Person;
use App\Models\TerritorialUnit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class OrganizationImporter extends Importer
{
    protected static ?string $model = Organization::class;

    /** @var Organization */
    protected ?Model $record;

    protected static bool $shouldPreventFormulaInjection = true;

    /**
     * @return array<ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('edrpou')
                ->label('Ідентифікатор')
                ->guesses(['Id'])
                ->requiredMapping()
                ->rules(['required', 'string', 'size:8']),
            ImportColumn::make('full_name')
                ->label('Повна назва')
                ->guesses(['name'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('description')
                ->label('Опис')
                ->requiredMapping()
                ->rules(['nullable', 'string', 'max:300']),
            ImportColumn::make('constituent_doc')
                ->label('Установчий документ')
                ->requiredMapping()
                ->guesses(['constituentDocUrl'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url:http,https', 'max:2048']),
            ImportColumn::make('homepage')
                ->label('Офіційний веб-сайт')
                ->requiredMapping()
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url:http,https', 'max:2048']),
            ImportColumn::make('social_accounts')
                ->label('Посилання на сторінки в соціальних медіа')
                ->requiredMapping()
                ->guesses(['socialAccount'])
                ->array()
                ->castStateUsing(self::normalizeSocialAccounts(...))
                ->rules(['nullable', 'array'])
                ->nestedRecursiveRules(['url:http,https']),
            ImportColumn::make('contact_point')
                ->label('Контактна особа')
                ->requiredMapping()
                ->guesses(['contactPointName'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('phone')
                ->label('Телефон')
                ->requiredMapping()
                ->guesses(['contactPointTelephone'])
                ->castStateUsing(ImportHelper::normalizePhoneCastState(...))
                ->rules(['required', 'string', 'max:255', 'not_regex:/,/']),
            ImportColumn::make('email')
                ->label('Електронна пошта')
                ->guesses(['contactPointEmail'])
                ->requiredMapping()
                ->castStateUsing(ImportHelper::normalizeEmailCastState(...))
                ->rules(['required', 'email', 'max:255']),
            ImportColumn::make('opening_hours')
                ->label('Графік роботи')
                ->requiredMapping()
                ->guesses(['openingHours'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:2048']),
            ImportColumn::make('availability_restriction')
                ->label('Обмеження доступності')
                ->requiredMapping()
                ->guesses(['availabilityRestriction'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:2048']),
            ImportColumn::make('logo')
                ->label('Логотип')
                ->requiredMapping()
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url:http,https', 'max:2048']),
            ImportColumn::make('headName')
                ->label('Ім’я керівника')
                ->requiredMapping()
                ->rules(['string', 'max:255', 'required'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('headPost')
                ->label('Посада керівника')
                ->requiredMapping()
                ->rules(['string', 'max:255', 'required'])
                ->fillRecordUsing(fn (): null => null),
            ImportColumn::make('parentOrganization')
                ->label('Ідентифікатор організації вищого рівня')
                ->requiredMapping()
                ->guesses(['subOrgOfId'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'size:8'])
                ->relationship(resolveUsing: function (mixed $state): ?Organization {
                    return Organization::query()
                        ->where('edrpou', $state)
                        ->first();
                }),
            ImportColumn::make('territorialUnit')
                ->label('КАТОТТГ')
                ->requiredMapping()
                ->guesses(['CATUTTC'])
                ->rules(['required', 'string', 'size:19'])
                ->relationship(resolveUsing: function (array $data): ?TerritorialUnit {
                    return TerritorialUnit::query()
                        ->where('katottg_code', $data['territorialUnit'])
                        ->whereLevel(4)
                        ->first();
                }),
            ImportColumn::make('short_name')
                ->label('Коротка назва')
                ->guesses(['shortName'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:255']),
            ImportColumn::make('group')
                ->label('Група')
                ->guesses(['groupName'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:255'])
                ->relationship(resolveUsing: fn (mixed $state): ?OrganizationGroup => OrganizationGroup::query()
                    ->where('name', $state)
                    ->first()),
            ImportColumn::make('additional_contacts')
                ->label('Додаткові контакти')
                ->guesses(['additionalContacts'])
                ->array()
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'array'])
                ->nestedRecursiveRules(['string', 'max:255']),
        ];
    }

    public function resolveRecord(): Organization
    {
        $organizationId = $this->data['edrpou'] ?? null;

        if (! is_string($organizationId) || blank($organizationId)) {
            throw new LogicException('Для імпорту організації потрібен код ЄДРПОУ.');
        }

        return Organization::query()->firstOrNew(['edrpou' => $organizationId]);
    }

    protected function afterSave(): void
    {
        if (array_key_exists('headName', $this->data) || array_key_exists('headPost', $this->data)) {
            $this->saveHeadRelationship($this->record);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return ImportHelper::getCompletedNotificationBody($import);
    }

    private static function normalizeSocialAccounts(mixed $state, mixed $originalState): mixed
    {
        $state = ImportHelper::normalizeNullCastState($state, $originalState);

        return is_array($state)
            ? array_map(static fn (mixed $url): mixed => is_string($url) ? trim($url) : $url, $state)
            : $state;
    }

    private function saveHeadRelationship(Organization $organization): void
    {
        $headName = $this->data['headName'] ?? null;
        $headPost = $this->data['headPost'] ?? null;

        [$lastName, $firstName, $middleName] = ImportHelper::parseName($headName);

        /** @var Person $person */
        $person = Person::query()->firstOrCreate([
            'last_name' => $lastName,
            'first_name' => $firstName,
            'middle_name' => $middleName,
        ]);

        /** @var Employee $employee */
        $employee = $organization->employees()->firstOrCreate(
            ['person_id' => $person->getKey()],
            ['position' => $headPost]
        );

        $employee->position = $headPost;
        $employee->is_post = true;
        $employee->save();

        $organization->head()->associate($employee);
        $organization->save();
    }
}
