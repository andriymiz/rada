<?php

namespace App\Observers;

use App\Enums\UserRole;
use App\Models\User;

class UserObserver
{
    public function creating(User $user): void
    {
        if (! User::query()->exists()) {
            $user->role = UserRole::Admin;

            return;
        }

        $user->role ??= UserRole::Employee;
    }
}
