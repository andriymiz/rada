<?php

namespace App\Models;

use App\Enums\DatasetUpdateFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dataset_id
 * @property string $description
 * @property DatasetUpdateFrequency|null $accrual_periodicity
 * @property array<int, string> $keywords
 * @property string $purpose
 * @property string $contact_point_name
 * @property string $contact_point_email
 * @property-read Dataset $dataset
 */
#[Fillable([
    'description',
    'accrual_periodicity',
    'keywords',
    'purpose',
    'contact_point_name',
    'contact_point_email',
])]
class DatasetMeta extends Model
{
    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'accrual_periodicity' => DatasetUpdateFrequency::class,
        ];
    }

    /** @return BelongsTo<Dataset, $this> */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}
