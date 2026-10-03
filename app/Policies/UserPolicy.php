<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.manage');
    }

    /** Only a super-admin may edit another super-admin. */
    public function update(User $actor, User $user): bool
    {
        if ($user->hasRole(Permissions::superAdminRole()) && ! $actor->hasRole(Permissions::superAdminRole())) {
            return false;
        }

        return $actor->can('users.manage');
    }

    /** Nobody can deactivate their own account. */
    public function toggleActive(User $actor, User $user): bool
    {
        return $actor->isNot($user) && $this->update($actor, $user);
    }

    public function resetSecurity(User $actor, User $user): bool
    {
        if ($user->hasRole(Permissions::superAdminRole()) && ! $actor->hasRole(Permissions::superAdminRole())) {
            return false;
        }

        return $actor->isNot($user) && $actor->can('users.reset_security');
    }
}
