<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    /**
     * Who may edit, toggle the login of, delete or reset the password of a
     * user. Taking over an account (change its email, send a reset) hands
     * over its permissions, so:
     * - a superuser may manage anyone;
     * - nobody else may manage a user with an effective admin or superuser grant;
     * - an admin may manage everyone else;
     * - without admin, only users whose permissions the actor holds as well.
     */
    public function manage(User $actor, User $target): bool
    {
        if ($actor->isSuperuser()) {
            return true;
        }
        if ($target->hasAccess(Permission::ADMIN)) {
            return false;
        }
        if ($actor->hasAccess(Permission::ADMIN)) {
            return true;
        }

        $own = $actor->effectivePermissions();
        foreach ($target->effectivePermissions() as $permission => $held) {
            if ($held && ! $own[$permission]) {
                return false;
            }
        }

        return true;
    }
}
