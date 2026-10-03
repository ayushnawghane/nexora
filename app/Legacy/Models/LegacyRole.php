<?php

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Stack roles. */
class LegacyRole extends LegacyModel
{
    protected $table = 'roles';

    /**
     * @return BelongsToMany<LegacyPermission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(LegacyPermission::class, 'roles_permissions', 'role_id', 'permission_id');
    }

    /**
     * @return BelongsToMany<LegacyUser, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(LegacyUser::class, 'users_roles', 'role_id', 'user_id');
    }
}
