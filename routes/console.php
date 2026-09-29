<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment('Build a reliable foundation for RADA.');
})->purpose('Display a short RADA message');
