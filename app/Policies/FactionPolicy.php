<?php

namespace App\Policies;

use App\Models\Faction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Faction $faction): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Faction $faction): bool
    {
        return true;
    }

    public function delete(User $user, Faction $faction): Response
    {
        if ($faction->deputies()->exists()) {
            return Response::deny('Неможливо видалити фракцію, до якої прив’язані депутати.');
        }

        return Response::allow();
    }
}
