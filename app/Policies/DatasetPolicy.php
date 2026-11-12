<?php

namespace App\Policies;

use App\Models\CouncilOrganization;
use App\Models\User;

class DatasetPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin()
            && CouncilOrganization::query()->whereNotNull('open_data_id')->exists();
    }
}
