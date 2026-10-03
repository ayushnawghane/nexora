<?php

namespace App\Legacy\Importers;

use App\Legacy\Importer;
use App\Legacy\Models\LegacyPermission;
use App\Legacy\Models\LegacyRole;
use App\Legacy\Models\LegacyUser;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles, who holds them, and the permissions they carry, translated through
 * config('legacy.permission_map'). Needs the organisation area first (users).
 * The super-admin role is never touched.
 */
class RolesImporter extends Importer
{
    public function area(): string
    {
        return 'roles';
    }

    public function description(): string
    {
        return 'Roles, role membership and mapped permissions';
    }

    protected function import(): void
    {
        $known = Permissions::all();
        $unmapped = [];

        foreach (LegacyRole::query()->with(['permissions', 'users'])->orderBy('id')->get() as $row) {
            $this->report->read('roles');
            $name = Str::limit((string) ($row->text('name') ?? "Role {$row->id}"), 120, '');
            if ($name === Permissions::superAdminRole()) {
                $this->report->reject('roles', $row->id, 'Named like the protected super-admin role.');

                continue;
            }

            $this->claim(Role::class, $row->id, 'name', $name);
            /** @var Role $role */
            $role = $this->upsert(Role::class, $row->id, ['name' => $name, 'guard_name' => 'web'], 'roles');
            if (! $row->isLegacyActive()) {
                $this->report->warn('roles', $row->id, 'Inactive in Stack; imported with its members so access can be reviewed.');
            }

            $role->syncPermissions($this->map($row->permissions, $known, $unmapped));

            $members = $row->users->map(fn (LegacyUser $u) => $this->idFor(User::class, (int) $u->getKey()))->filter()->values();
            $this->report->read('role memberships', $row->users->count());
            foreach (User::query()->withTrashed()->whereKey($members)->get() as $user) {
                if (! $user->hasRole($role)) {
                    $user->assignRole($role);
                    $this->report->saved('role memberships', 'created');
                } else {
                    $this->report->saved('role memberships', 'unchanged');
                }
            }
        }

        // Permissions given to users directly in Stack.
        foreach (LegacyUser::query()->with('permissions')->has('permissions')->get() as $row) {
            $user = User::query()->withTrashed()->where('legacy_id', $row->getKey())->first();
            if ($user === null) {
                continue;
            }
            $this->report->read('direct permissions');
            $user->syncPermissions($this->map($row->permissions, $known, $unmapped));
            $this->report->saved('direct permissions', 'updated');
        }

        ksort($unmapped);
        $this->report->total('Legacy permissions with no Nexora equivalent (not granted)', (string) count($unmapped));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  Collection<int, LegacyPermission>  $permissions
     * @param  list<string>  $known
     * @param  array<string, true>  $unmapped
     * @return list<string>
     */
    private function map(Collection $permissions, array $known, array &$unmapped): array
    {
        $map = config('legacy.permission_map');
        $granted = [];

        foreach ($permissions as $permission) {
            $slug = (string) $permission->getAttribute('slug');
            if (! isset($map[$slug])) {
                $unmapped[$slug] = true;

                continue;
            }
            foreach ($map[$slug] as $name) {
                if (in_array($name, $known, true)) {
                    $granted[$name] = true;
                }
            }
        }

        return array_keys($granted);
    }
}
