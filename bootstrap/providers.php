<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\PublicPanelProvider;
use App\Providers\Filament\RadaPanelProvider;

return [
    AppServiceProvider::class,
    PublicPanelProvider::class,
    RadaPanelProvider::class,
];
