<?php

namespace App\Models;

use Database\Factories\PoliticalPartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property-read string $short_name */
#[Fillable(['name', 'edrpou'])]
class PoliticalParty extends Model
{
    /** @use HasFactory<PoliticalPartyFactory> */
    use HasFactory;

    /** @return Attribute<string, never> */
    protected function shortName(): Attribute
    {
        return Attribute::get(fn (): string => Str::of($this->name)
            ->replace(['"', '«', '»', '“', '”', '„'], '')
            ->trim()
            ->replaceMatches("/^(?:(?:політична\\s+партія|всеукраїнське\\s+об['’ʼ]єднання)(?:\\s+|$))+/iu", '')
            ->trim()
            ->toString());
    }

    /** @return HasMany<Deputy, $this> */
    public function deputies(): HasMany
    {
        return $this->hasMany(Deputy::class);
    }
}
