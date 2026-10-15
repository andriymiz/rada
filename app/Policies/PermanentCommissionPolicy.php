<?php

namespace App\Policies;

use App\Models\PermanentCommission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PermanentCommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PermanentCommission $permanentCommission): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, PermanentCommission $permanentCommission): bool
    {
        return true;
    }

    public function delete(User $user, PermanentCommission $permanentCommission): Response
    {
        if ($permanentCommission->deputies()->exists()) {
            return Response::deny('Неможливо видалити комісію, до якої прив’язані депутати.');
        }

        return Response::allow();
    }
}
