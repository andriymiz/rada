<?php

namespace App\Filament\Exports;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ExportHelper
{
    public static function getCompletedNotificationBody(Export $export): string
    {
        return class_basename($export->exporter)
            .' - Успішно експортовано '.Number::format($export->successful_rows)
            .' із '.Number::format($export->total_rows)
            .' '.trans_choice('{1} запис|[2,4] записи|[5,*] записів', $export->total_rows).'.';
    }
}
