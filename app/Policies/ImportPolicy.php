<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function view(User $user, Import $import): bool
    {
        return $import->uploaded_by === $user->id;
    }

    public function update(User $user, Import $import): bool
    {
        return $import->uploaded_by === $user->id;
    }

    public function delete(User $user, Import $import): bool
    {
        return $import->uploaded_by === $user->id;
    }
}
