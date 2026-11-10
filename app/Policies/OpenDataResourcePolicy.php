<?php

namespace App\Policies;

use App\Models\CouncilOrganization;
use App\Models\User;

class OpenDataResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            && CouncilOrganization::query()->whereNotNull('open_data_id')->exists();
    }
}
