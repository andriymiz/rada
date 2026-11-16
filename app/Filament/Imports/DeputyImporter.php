<?php

namespace App\Filament\Imports;

use App\Enums\Gender;
use App\Models\Deputy;
use App\Models\Faction;
use App\Models\PermanentCommission;
use App\Models\Person;
use App\Models\PoliticalParty;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use LogicException;

class DeputyImporter extends Importer
{
    protected static ?string $model = Deputy::class;

    /**
     * @return array<ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('number')
                ->label('Порядковий номер')
                ->guesses(['number', 'id'])
                ->integer()
                ->requiredMapping()
                ->rules(['required', 'integer', 'min:1']),

            ImportColumn::make('voting_identifier')
                ->label('Номер пульта')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->guesses(['votingIdentifier'])
                ->rules(['nullable', 'string', 'max:255']),

            ImportColumn::make('last_name')
                ->label('Прізвище')
                ->guesses(['familyName'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('first_name')
                ->label('Імʼя')
                ->guesses(['name'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('middle_name')
                ->label('По батькові')
                ->guesses(['additionalName'])
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('gender')
                ->label('Стать')
                ->requiredMapping()
                ->enum(Gender::class)
                ->rules(['required'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('date_of_birth')
                ->label('Дата народження')
                ->guesses(['birthDate'])
                ->requiredMapping()
                ->rules(['required', 'date'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('image')
                ->label('Фото')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:2048']),

            ImportColumn::make('biography')
                ->label('Біографічні дані')
                ->requiredMapping()
                ->rules(['required', 'string']),

            ImportColumn::make('partyName')
                ->label('Назва партії')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('partyId')
                ->label('Ідентифікатор партії')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:8'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('email')
                ->label('Електронна пошта')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'email', 'max:255']),

            ImportColumn::make('telephone')
                ->label('Номер телефону')
                ->castStateUsing(ImportHelper::normalizePhoneCastState(...))
                ->rules(['nullable', 'string', 'max:255', 'not_regex:/,/'])
                ->fillRecordUsing(fn (Deputy $record, mixed $state) => $record->phone = $state),

            ImportColumn::make('homepage')
                ->label('Веб-сторінка')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'url', 'max:2048']),

            ImportColumn::make('account')
                ->label('Соціальні медіа')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->multiple(',')
                ->rules(['nullable', 'array'])
                ->nestedRecursiveRules(['url'])
                ->fillRecordUsing(fn (Deputy $record, mixed $state) => $record->social_accounts = $state),

            ImportColumn::make('openingHours')
                ->label('Графік прийому')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:2048'])
                ->fillRecordUsing(fn (Deputy $record, mixed $state) => $record->opening_hours = $state),

            ImportColumn::make('availabilityRestriction')
                ->label('Обмеження доступності')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:2048'])
                ->fillRecordUsing(fn (Deputy $record, mixed $state) => $record->availability_restriction = $state),

            ImportColumn::make('factionName')
                ->label('Назва фракції')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('factionPost')
                ->label('Посада у фракції')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(function (Deputy $record, mixed $state): void {
                    $record->faction_position = ($state === 'Член депутатської фракції') ? null : $state;
                }),

            ImportColumn::make('start_date')
                ->label('Дата початку')
                ->guesses(['startDate'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->ignoreBlankState()
                ->rules(['nullable', 'date']),

            ImportColumn::make('commissionName')
                ->label('Назва постійної комісії')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(fn (): null => null),

            ImportColumn::make('commissionPost')
                ->label('Посада у постійній комісії')
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'string', 'max:255'])
                ->fillRecordUsing(function (Deputy $record, mixed $state): void {
                    $record->commission_position = ($state === 'Член постійної комісії') ? null : $state;
                }),

            ImportColumn::make('area_id')
                ->label('Виборчий округ')
                ->guesses(['areaId'])
                ->castStateUsing(ImportHelper::normalizeNullCastState(...))
                ->rules(['nullable', 'integer', 'min:0']),
        ];

        // Place and reception schedule (CATUTTC, addressPostCode, addressAdminUnitL1, addressAdminUnitL2,
        // addressAdminUnitL3, addressAdminUnitL4, addressPostName, addressThoroughfare, addressLocatorDesignator,
        // addressLocatorBuilding, addressLocatorName)
        // are intentionally not implemented yet.
    }

    public function resolveRecord(): Deputy
    {
        $convocationId = $this->getConvocationId();

        $number = $this->data['number'] ?? null;
        if ($number !== null) {
            $existing = Deputy::query()
                ->where('convocation_id', $convocationId)
                ->where('number', $number)
                ->first();

            if ($existing instanceof Deputy) {
                return $existing;
            }
        }

        $existing = Deputy::query()
            ->where('convocation_id', $convocationId)
            ->whereHas('person', function ($query): void {
                $query
                    ->where('last_name', $this->data['last_name'] ?? null)
                    ->where('first_name', $this->data['first_name'] ?? null)
                    ->where('middle_name', $this->data['middle_name'] ?? null);
            })
            ->first();

        if ($existing instanceof Deputy) {
            return $existing;
        }

        $person = $this->findPerson();
        if ($person instanceof Person) {
            $existing = Deputy::query()
                ->where('convocation_id', $convocationId)
                ->where('person_id', $person->getKey())
                ->first();

            if ($existing instanceof Deputy) {
                return $existing;
            }
        }

        $deputy = new Deputy;
        $deputy->convocation_id = $convocationId;

        return $deputy;
    }

    protected function beforeSave(): void
    {
        $convocationId = $this->getConvocationId();
        $record = $this->getDeputyRecord();
        $record->convocation_id = $convocationId;

        $this->savePerson();
        $this->savePoliticalParty();
        $this->saveFaction($convocationId);
        $this->savePermanentCommission($convocationId);
    }

    protected function savePerson(): void
    {
        $record = $this->getDeputyRecord();
        $person = $record->person ?? $this->findPerson();

        if (! $person instanceof Person) {
            $person = new Person;
        }

        $person->last_name = $this->data['last_name'] ?? null;
        $person->first_name = $this->data['first_name'] ?? null;
        $person->middle_name = $this->data['middle_name'] ?? null;
        $person->date_of_birth = $this->data['date_of_birth'] ?? null;
        $person->gender = $this->data['gender'] ?? null;
        $person->save();

        $record->person()->associate($person);
    }

    protected function savePoliticalParty(): void
    {
        $record = $this->getDeputyRecord();
        $partyName = $this->data['partyName'] ?? null;
        $partyId = $this->data['partyId'] ?? null;

        if ($partyName === null && $partyId === null) {
            return;
        }

        $party = null;

        if ($partyId !== null) {
            $party = PoliticalParty::query()->where('edrpou', $partyId)->first();
        }

        if (! $party instanceof PoliticalParty && $partyName !== null) {
            $party = PoliticalParty::query()->where('name', $partyName)->first();
        }

        if (! $party instanceof PoliticalParty && $partyName !== null) {
            $party = PoliticalParty::query()->create([
                'name' => $partyName,
                'edrpou' => $partyId,
            ]);
        } elseif ($party instanceof PoliticalParty && $partyId !== null && blank($party->edrpou)) {
            $party->update(['edrpou' => $partyId]);
        }

        if ($party instanceof PoliticalParty) {
            $record->politicalParty()->associate($party);
        }
    }

    protected function saveFaction(int $convocationId): void
    {
        $record = $this->getDeputyRecord();
        $factionName = $this->data['factionName'] ?? null;

        if ($factionName === null || $factionName === 'Позафракційний') {
            $record->faction()->dissociate();
            $record->faction_position = null;

            return;
        }

        $faction = Faction::query()->firstOrCreate([
            'convocation_id' => $convocationId,
            'name' => $factionName,
        ]);

        $record->faction()->associate($faction);

        $factionPost = $this->data['factionPost'] ?? null;
        if ($factionPost !== null && $factionPost !== 'Член депутатської фракції') {
            $record->faction_position = $factionPost;
        } else {
            $record->faction_position = null;
        }
    }

    protected function savePermanentCommission(int $convocationId): void
    {
        $record = $this->getDeputyRecord();
        $commissionName = $this->data['commissionName'] ?? null;

        if ($commissionName === null) {
            $record->permanentCommission()->dissociate();
            $record->commission_position = null;

            return;
        }

        $commission = PermanentCommission::query()->firstOrCreate([
            'convocation_id' => $convocationId,
            'name' => $commissionName,
        ]);

        $record->permanentCommission()->associate($commission);

        $commissionPost = $this->data['commissionPost'] ?? null;
        if ($commissionPost !== null && $commissionPost !== 'Член постійної комісії') {
            $record->commission_position = $commissionPost;
        } else {
            $record->commission_position = null;
        }
    }

    protected function findPerson(): ?Person
    {
        return Person::query()
            ->where(collect($this->data)
                ->only(['last_name', 'first_name', 'middle_name'])
                ->toArray())
            ->orderBy('id')
            ->first();
    }

    protected function getDeputyRecord(): Deputy
    {
        $record = $this->record;

        if (! $record instanceof Deputy) {
            throw new LogicException('The importer record must be a deputy.');
        }

        return $record;
    }

    /** @return int<0, max> */
    protected function getConvocationId(): int
    {
        $convocationId = (int) $this->options['convocation']->getKey();

        if ($convocationId < 0) {
            throw new LogicException('The convocation ID must be a non-negative integer.');
        }

        return $convocationId;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return ImportHelper::getCompletedNotificationBody($import);
    }
}
