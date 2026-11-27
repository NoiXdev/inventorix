<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    /**
     * Editing, toggling login, deleting or resetting the password of a user who
     * holds an effective admin or superuser grant is for superusers only, so
     * `users.edit` cannot be used to take over a privileged account.
     */
    public function manage(User $actor, User $target): bool
    {
        if ($actor->isSuperuser()) {
            return true;
        }

        return ! $target->hasAccess(Permission::ADMIN);
    }
}
