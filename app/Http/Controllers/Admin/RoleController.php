<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\RoleRequest;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions_count' => $role->name === Permissions::superAdminRole() ? count(Permissions::all()) : $role->permissions_count,
                'locked' => $role->name === Permissions::superAdminRole(),
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'can' => ['manage' => $request->user()->can('create', Role::class)],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Admin/Roles/Form', [
            'role' => null,
            'sections' => Permissions::grouped(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
            $role->syncPermissions($request->validated('permissions', []));
        });

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('Admin/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name'),
                'users_count' => $role->users()->count(),
            ],
            'sections' => Permissions::grouped(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role) {
            $role->update(['name' => $request->validated('name')]);
            $role->syncPermissions($request->validated('permissions', []));
        });

        activity()->performedOn($role)->event('updated')
            ->withProperties(['permissions' => $request->validated('permissions', [])])
            ->log('Role permissions updated');

        return redirect()->route('roles.edit', $role)->with('success', 'Role saved.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to users. Move them to another role first.');
        }

        $role->delete();

        return redirect()->route('roles.index')->with('success', 'Role deleted.');
    }
}
