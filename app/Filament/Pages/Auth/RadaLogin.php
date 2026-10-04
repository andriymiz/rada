<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;

class RadaLogin extends Login
{
    public function hasLogo(): bool
    {
        return false;
    }
}
