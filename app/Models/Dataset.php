<?php

namespace App\Models;

use App\Enums\DatasetRecommendation;
use App\Enums\DatasetStatus;
use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string|null $external_id
 * @property DatasetRecommendation|null $rec_code
 * @property DatasetStatus $status
 * @property string|null $status_note
 * @property string|null $info_owner
 * @property int|null $group_id
 * @property int $sort
 * @property-read DatasetGroup|null $group
 * @property-read DatasetMeta|null $meta
 */
#[Fillable([
    'rec_code',
    'external_id',
    'title',
    'publisher_id',
    'group_id',
    'status',
    'status_note',
    'info_owner',
])]
class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Dataset $dataset): void {
            $dataset->sort ??= ((int) static::query()
                ->where('group_id', $dataset->group_id)
                ->max('sort')) + 1;
        });
    }

    protected function casts(): array
    {
        return [
            'rec_code' => DatasetRecommendation::class,
            'status' => DatasetStatus::class,
        ];
    }

    /** @return Attribute<?non-falsy-string, never> */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): ?string => filled($this->external_id)
            ? rtrim(config('services.open_data.base_url'), '/').'/dataset/'.$this->external_id
            : null);
    }

    /** @return BelongsTo<CouncilOrganization, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(CouncilOrganization::class, 'publisher_id');
    }

    /** @return BelongsTo<DatasetGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(DatasetGroup::class, 'group_id');
    }

    /** @return HasOne<DatasetMeta, $this> */
    public function meta(): HasOne
    {
        return $this->hasOne(DatasetMeta::class);
    }

    /** @return HasMany<DatasetResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(DatasetResource::class);
    }
}
