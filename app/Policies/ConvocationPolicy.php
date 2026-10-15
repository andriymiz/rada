<?php

namespace App\Policies;

use App\Models\Convocation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConvocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Convocation $convocation): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Convocation $convocation): bool
    {
        return true;
    }

    public function delete(User $user, Convocation $convocation): Response
    {
        $blockers = array_filter([
            $convocation->deputies()->exists() ? 'депутати' : null,
            $convocation->sessions()->exists() ? 'сесії' : null,
            $convocation->permanentCommissions()->exists() ? 'постійні комісії' : null,
            $convocation->factions()->exists() ? 'фракції' : null,
        ]);

        if ($blockers !== []) {
            return Response::deny('Спочатку видаліть пов’язані записи: '.implode(', ', $blockers).'.');
        }

        return Response::allow();
    }
}
