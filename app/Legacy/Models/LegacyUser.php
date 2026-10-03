<?php

namespace App\Legacy\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Stack users (staff). product_map_id is a comma-separated product list. */
class LegacyUser extends LegacyModel
{
    protected $table = 'users';

    /**
     * Permissions given to the user directly, on top of their roles.
     *
     * @return BelongsToMany<LegacyPermission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(LegacyPermission::class, 'users_permissions', 'user_id', 'permission_id');
    }
}
