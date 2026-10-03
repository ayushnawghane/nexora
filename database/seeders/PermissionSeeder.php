<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Syncs config/permissions.php into the database. Safe to run repeatedly. */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $declared = Permissions::all();

        foreach ($declared as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Permission::query()->whereNotIn('name', $declared)->delete();

        Role::findOrCreate(Permissions::superAdminRole(), 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
