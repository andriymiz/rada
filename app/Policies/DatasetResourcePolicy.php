<?php

namespace App\Policies;

use App\Models\DatasetResource;
use App\Models\User;

class DatasetResourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, DatasetResource $datasetResource): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, DatasetResource $datasetResource): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, DatasetResource $datasetResource): bool
    {
        return $user->isAdmin();
    }
}
