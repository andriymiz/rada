<?php

namespace App\Models;

use App\Enums\TerritorialUnitCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $system_number
 * @property int|null $parent_number
 * @property string $katottg_code
 * @property TerritorialUnitCategory $category
 * @property string $name
 */
#[Fillable([
    'system_number',
    'parent_number',
    'katottg_code',
    'category',
    'name',
])]
class TerritorialUnit extends Model
{
    protected $primaryKey = 'system_number';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'category' => TerritorialUnitCategory::class,
            'system_number' => 'integer',
            'parent_number' => 'integer',
        ];
    }

    /**
     * @param  Builder<TerritorialUnit>  $query
     */
    #[Scope]
    protected function whereLevel(Builder $query, int $level): void
    {
        $query->whereIn('category', match ($level) {
            1 => [
                TerritorialUnitCategory::FirstLevel->value,
                TerritorialUnitCategory::SpecialStatusCity->value,
            ],
            2 => [TerritorialUnitCategory::SecondLevel->value],
            3 => [TerritorialUnitCategory::ThirdLevel->value],
            4 => [
                TerritorialUnitCategory::City->value,
                TerritorialUnitCategory::Settlement->value,
                TerritorialUnitCategory::Village->value,
            ],
            5 => [TerritorialUnitCategory::CityDistrict->value],
            default => throw new \InvalidArgumentException("Invalid level: $level"),
        });
    }

    /**
     * @return BelongsTo<TerritorialUnit, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_number', 'system_number');
    }

    /**
     * @return HasMany<TerritorialUnit, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_number', 'system_number');
    }
}
