<?php

namespace App\Policies;

use App\Models\DatasetGroup;
use App\Models\User;

class DatasetGroupPolicy
{
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DatasetGroup $datasetGroup): bool
    {
        return $user->isAdmin();
    }
}
