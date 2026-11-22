<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

class ImportHelper
{
    public static function getCompletedNotificationBody(Import $import): string
    {
        return class_basename($import->importer)
            .' - Успішно імпортовано '.Number::format($import->successful_rows)
            .' із '.Number::format($import->total_rows)
            .' '.trans_choice('{1} запис|[2,4] записи|[5,*] записів', $import->total_rows).'.';
    }

    public static function normalizeNullCastState(mixed $state, mixed $originalState): mixed
    {
        return is_string($originalState) && Str::of($originalState)->trim()->lower()->exactly('null')
            ? (is_array($state) ? [] : null)
            : $state;
    }

    public static function normalizePhoneCastState(mixed $state, mixed $originalState): mixed
    {
        $state = self::normalizeNullCastState($state, $originalState);

        if (! is_string($state)) {
            return $state;
        }

        $state = trim($state);
        if (preg_match("/^'[+=@-]/", $state) === 1) {
            $state = substr($state, 1);
        }

        $phone = ltrim($state, "'+‘’ \t\n\r\0\x0B");

        return str_starts_with($phone, '380') ? '+'.$phone : $state;
    }

    public static function normalizeEmailCastState(mixed $state, mixed $originalState): mixed
    {
        $state = self::normalizeNullCastState($state, $originalState);

        return is_string($state) ? trim($state) : $state;
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
