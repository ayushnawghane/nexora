<?php

namespace App\Legacy\Models;

/** Stack permissions: one per screen, identified by `slug`. */
class LegacyPermission extends LegacyModel
{
    protected $table = 'permissions';
}
