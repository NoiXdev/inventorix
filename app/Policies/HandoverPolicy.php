<?php

namespace App\Policies;

use App\Models\Handover;
use App\Models\User;

class HandoverPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->login_enabled;
    }

    public function view(User $user, Handover $handover): bool
    {
        return (bool) $user->login_enabled;
    }

    public function create(User $user): bool
    {
        return false;  // creation only via service, not via resource form
    }

    public function update(User $user, Handover $handover): bool
    {
        return false;  // immutable
    }

    public function delete(User $user, Handover $handover): bool
    {
        return false;  // immutable
    }
}
