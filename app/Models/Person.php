<?php

namespace App\Models;

use App\Enums\Gender;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property-read string $full_name
 * @property-read string $short_name
 */
#[Fillable([
    'last_name',
    'first_name',
    'middle_name',
    'gender',
    'date_of_birth',
])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'date',
        ];
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->last_name, $this->first_name, $this->middle_name])
            ->filter(filled(...))
            ->map(fn (?string $name): string => trim($name ?? ''))
            ->implode(' '));
    }

    /** @return Attribute<string, never> */
    protected function shortName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->first_name, $this->middle_name])
            ->filter(filled(...))
            ->map(fn (?string $name): string => Str::of($name ?? '')->trim()->substr(0, 1)->upper()->append('.')->toString())
            ->prepend($this->last_name)
            ->filter(filled(...))
            ->map(fn (?string $name): string => trim($name ?? ''))
            ->implode(' '));
    }

    /** @return HasMany<Deputy, $this> */
    public function deputies(): HasMany
    {
        return $this->hasMany(Deputy::class);
    }
}
