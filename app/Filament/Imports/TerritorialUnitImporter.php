<?php

namespace App\Filament\Imports;

use App\Enums\TerritorialUnitCategory;
use App\Models\TerritorialUnit;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;

class TerritorialUnitImporter extends Importer
{
    protected static ?string $model = TerritorialUnit::class;

    /**
     * @return array<ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('1')
                ->label('Перший рівень')
                ->requiredMapping()
                ->rules(['required', 'string', 'regex:/^UA\d{17}$/'])
                ->fillRecordUsing(self::recordByLevel(...)),
            ImportColumn::make('2')
                ->label('Другий рівень')
                ->requiredMapping()
                ->rules(['nullable', 'string', 'regex:/^UA\d{17}$/'])
                ->fillRecordUsing(self::recordByLevel(...)),
            ImportColumn::make('3')
                ->label('Третій рівень')
                ->requiredMapping()
                ->rules(['nullable', 'string', 'regex:/^UA\d{17}$/'])
                ->fillRecordUsing(self::recordByLevel(...)),
            ImportColumn::make('4')
                ->label('Четвертий рівень')
                ->requiredMapping()
                ->rules(['nullable', 'string', 'regex:/^UA\d{17}$/'])
                ->fillRecordUsing(self::recordByLevel(...)),
            ImportColumn::make('5')
                ->label('Додатковий рівень')
                ->requiredMapping()
                ->rules(['nullable', 'string', 'regex:/^UA\d{17}$/'])
                ->fillRecordUsing(self::recordByLevel(...)),
            ImportColumn::make('category')
                ->label('Категорія об’єкта')
                ->requiredMapping()
                ->enum(TerritorialUnitCategory::class)
                ->rules(['required', 'string']),
            ImportColumn::make('name')
                ->label('Назва об’єкта')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:255']),
        ];
    }

    public function resolveRecord(): TerritorialUnit
    {
        $category = TerritorialUnitCategory::tryFrom($this->data['category']);
        $katottgCode = $this->data[(string) $category?->level()] ?? null;
        $number = (int) Str::substr($katottgCode, -6);

        return TerritorialUnit::query()->find($number) ?? new TerritorialUnit;
    }

    private static function recordByLevel(TerritorialUnit $record, ImportColumn $column, mixed $state, mixed $data): void
    {
        $level = TerritorialUnitCategory::tryFrom($data['category'])?->level() ?? 1;
        $mappedLevel = (int) $column->getName();

        if ($level === $mappedLevel) {
            $record->katottg_code = $state;
            $record->system_number = (int) Str::substr($state, -6);
        }

        if ($level === $mappedLevel + 1) {
            $record->parent_number = (int) Str::substr($state, -6);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return ImportHelper::getCompletedNotificationBody($import);
    }
}
