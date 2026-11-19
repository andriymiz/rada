<?php

namespace App\Filament\Imports;

use App\Enums\Gender;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Person;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use LogicException;

class OfficialImporter extends Importer
{
    protected static ?string $model = Employee::class;

    /**
     * @return array<ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('ПІБ')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('gender')
                ->label('Стать')
                ->requiredMapping()
                ->enum(Gender::class)
                ->rules(['required'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('positionName')
                ->label('Посада')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('orgId')
                ->label('Код організації')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:8'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('orgName')
                ->label('Назва організації')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('img')
                ->label('Фото')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url', 'max:2048'])
                ->fillRecordUsing(function (Employee $record, mixed $state): void {
                    $record->image = $state;
                }),

            ImportColumn::make('homepage')
                ->label('Домашня сторінка')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url', 'max:2048']),

            ImportColumn::make('socialAccount')
                ->label('Соціальна мережа')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->multiple(',')
                ->rules(['nullable', 'array'])
                ->nestedRecursiveRules(['url:http,https', 'max:2048'])
                ->fillRecordUsing(fn (Employee $record, mixed $state) => $record->social_accounts = $state),

            ImportColumn::make('phone')
                ->label('Телефон')
                ->guesses(['telephone'])
                ->requiredMapping()
                ->castStateUsing(ImportHelper::normalizePhoneCastState(...))
                ->rules(['required', 'string', 'max:255', 'not_regex:/,/']),

            ImportColumn::make('email')
                ->label('Електронна пошта')
                ->guesses(['email'])
                ->requiredMapping()
                ->castStateUsing(ImportHelper::normalizeEmailCastState(...))
                ->rules(['required', 'email', 'max:255']),

            ImportColumn::make('unitId')
                ->label('Код підрозділу')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('unitName')
                ->label('Назва підрозділу')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('openingHours')
                ->label('Графік прийому')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:2048'])
                ->fillRecordUsing(fn (Employee $record, mixed $state) => $record->opening_hours = $state),

            ImportColumn::make('availabilityRestriction')
                ->label('Обмеження доступності')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:2048'])
                ->fillRecordUsing(fn (Employee $record, mixed $state) => $record->availability_restriction = $state),
        ];
    }

    public function resolveRecord(): Employee
    {
        $organization = $this->resolveOrganization();
        [$lastName, $firstName, $middleName] = ImportHelper::parseName($this->data['name'] ?? '');

        return Employee::query()
            ->select('employees.*')
            ->join('people', 'people.id', '=', 'employees.person_id')
            ->where('people.last_name', $lastName)
            ->where('people.first_name', $firstName)
            ->where('people.middle_name', $middleName)
            ->whereBelongsTo($organization, 'organization')
            ->firstOrNew([], ['organization_id' => $organization->getKey()]);
    }

    protected function beforeSave(): void
    {
        $employee = $this->getEmployeeRecord();
        [$lastName, $firstName, $middleName] = ImportHelper::parseName($this->data['name'] ?? '');

        $person = $employee->person ?? new Person;
        $person->last_name = $lastName;
        $person->first_name = $firstName;
        $person->middle_name = $middleName;
        $person->gender = $this->data['gender'] ?? null;
        $person->save();

        $employee->person()->associate($person);
        $employee->organization()->associate($this->resolveOrganization());
        $employee->position = $this->data['positionName'] ?? '';
        $employee->is_post = true;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return ImportHelper::getCompletedNotificationBody($import);
    }

    private function resolveOrganization(): Organization
    {
        $organizationId = $this->data['orgId'] ?? null;
        $organizationName = $this->data['orgName'] ?? null;

        if (! is_string($organizationId) || ! is_string($organizationName) || blank($organizationId) || blank($organizationName)) {
            throw new LogicException('Ідентифікатор і назва організації є обов’язковими для імпорту працівника.');
        }

        $organization = Organization::query()
            ->where('edrpou', $organizationId)
            ->first();

        if (! $organization instanceof Organization) {
            throw new LogicException("Організацію з кодом [{$organizationId}] не знайдено. Створіть її перед імпортом.");
        }

        return $organization;
    }

    private function getEmployeeRecord(): Employee
    {
        if (! $this->record instanceof Employee) {
            throw new LogicException('Імпортер має працювати із записом працівника.');
        }

        return $this->record;
    }
}
