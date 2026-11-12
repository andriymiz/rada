<?php

namespace App\Filament\Imports;

use App\Enums\OpenDataResourceCode;
use App\Services\OpenDataPortal;
use Closure;
use Filament\Actions\Action;
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

    public static function downloadPortalResourceAction(OpenDataResourceCode $code): Closure
    {
        return static function (OpenDataPortal $openDataPortal) use ($code): ?Action {
            $downloadUrl = $openDataPortal->getResourceDownloadUrl($code);

            if ($downloadUrl === null) {
                return null;
            }

            return Action::make('downloadExample')
                ->label('Завантажити актуальний CSV з порталу відкритих даних')
                ->link()
                ->url($downloadUrl, true);
        };
    }

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
