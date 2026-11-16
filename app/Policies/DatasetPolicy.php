<?php

namespace App\Policies;

use App\Models\Dataset;
use App\Models\User;

class DatasetPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin()
            && filled(config('services.open_data.organization_id'));
    }

    public function delete(User $user, Dataset $dataset): bool
    {
        return $dataset->rec_code === null;
    }
}
