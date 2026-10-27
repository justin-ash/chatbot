<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    public function moderate(User $user): bool
    {
        return $user->canModerate();
    }

    public function managePayments(User $user): bool
    {
        return $user->isAdmin();
    }

    public function manageSettings(User $user): bool
    {
        return $user->isAdmin();
    }
}
