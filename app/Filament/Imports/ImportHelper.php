<?php

namespace App\Filament\Imports;

use Illuminate\Support\Str;

class ImportHelper
{
    public static function normalizeNullCastState(mixed $state, mixed $originalState): mixed
    {
        return is_string($originalState) && Str::of($originalState)->trim()->lower()->exactly('null')
            ? (is_array($state) ? [] : null)
            : $state;
    }

    /**
     * @return array<int, string|null>
     */
    public static function parseName(string $name): array
    {
        return Str::of($name)
            ->trim()
            ->replaceMatches('/\s+/u', ' ')
            ->explode(' ', 3)
            ->pad(3, null)
            ->all();
    }
}
