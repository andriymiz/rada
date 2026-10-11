<?php

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\DeputyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * @property-read string $full_name
 * @property-read string $short_name
 */
#[Fillable([
    'family_name',
    'first_name',
    'additional_name',
    'gender',
    'birth_date',
    'image',
    'homepage',
    'biography',
])]
class Deputy extends Model
{
    /** @use HasFactory<DeputyFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
        ];
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->family_name, $this->first_name, $this->additional_name])
            ->filter(filled(...))
            ->map(fn (?string $name): string => trim($name ?? ''))
            ->implode(' '));
    }

    /** @return Attribute<string, never> */
    protected function shortName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->first_name, $this->additional_name])
            ->filter(filled(...))
            ->map(fn (?string $name): string => Str::of($name ?? '')->trim()->substr(0, 1)->upper()->append('.')->toString())
            ->prepend($this->family_name)
            ->filter(filled(...))
            ->map(fn (?string $name): string => trim($name ?? ''))
            ->implode(' '));
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return MorphMany<ContactPoint, $this> */
    public function contactPoints(): MorphMany
    {
        return $this->morphMany(ContactPoint::class, 'contactable');
    }

    /** @return HasOne<DeputyReception, $this> */
    public function reception(): HasOne
    {
        return $this->hasOne(DeputyReception::class);
    }
}
