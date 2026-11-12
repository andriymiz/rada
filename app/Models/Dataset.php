<?php

namespace App\Models;

use App\Enums\DatasetUpdateFrequency;
use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property DatasetUpdateFrequency $accrual_periodicity
 * @property array<int, string> $keywords
 * @property array<int, string> $distribution_formats
 */
#[Fillable([
    'title',
    'description',
    'accrual_periodicity',
    'keywords',
    'purpose',
    'distribution_formats',
    'publisher_id',
    'responsible_official_id',
])]
class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Dataset $dataset): void {
            $dataset->external_id ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'accrual_periodicity' => DatasetUpdateFrequency::class,
            'keywords' => 'array',
            'distribution_formats' => 'array',
        ];
    }

    /** @return Attribute<non-falsy-string, never> */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => rtrim(config('services.open_data.base_url'), '/').'/dataset/'.$this->external_id);
    }

    /** @return BelongsTo<CouncilOrganization, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(CouncilOrganization::class, 'publisher_id');
    }

    /** @return BelongsTo<Official, $this> */
    public function responsibleOfficial(): BelongsTo
    {
        return $this->belongsTo(Official::class, 'responsible_official_id');
    }
}
