<?php

use App\Http\Controllers\RollCallImportPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/roll-call-imports/{rollCallImport}/pdf', RollCallImportPdfController::class)
    ->name('roll-call-imports.pdf');
