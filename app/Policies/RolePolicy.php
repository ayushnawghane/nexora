<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Permissions;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.manage');
    }

    /** The super-admin role is fixed: it always has every permission. */
    public function update(User $actor, Role $role): bool
    {
        return $role->name !== Permissions::superAdminRole() && $actor->can('roles.manage');
    }

    public function delete(User $actor, Role $role): bool
    {
        return $this->update($actor, $role);
    }
}
