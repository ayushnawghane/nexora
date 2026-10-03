<?php

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(PermissionSeeder::class));

test('roles need permission to view or manage', function () {
    signIn();

    $this->get('/admin/roles')->assertForbidden();
    $this->post('/admin/roles', ['name' => 'x'])->assertForbidden();
});

test('a role can be created with permissions', function () {
    signIn(permissions: ['roles.view', 'roles.manage']);

    $this->post('/admin/roles', ['name' => 'DT Operations', 'permissions' => ['transactions.view', 'deals.view']])
        ->assertRedirect('/admin/roles');

    $role = Role::findByName('dt-operations');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['deals.view', 'transactions.view']);
});

test('unknown permissions and reserved names are rejected', function () {
    signIn(permissions: ['roles.manage']);

    $this->post('/admin/roles', ['name' => 'ops', 'permissions' => ['everything.ever']])
        ->assertSessionHasErrors('permissions.0');
    $this->post('/admin/roles', ['name' => Permissions::superAdminRole()])
        ->assertSessionHasErrors('name');
});

test('the super-admin role cannot be edited or deleted', function () {
    signIn(permissions: ['roles.manage']);
    $role = Role::findByName(Permissions::superAdminRole());

    $this->get("/admin/roles/{$role->id}/edit")->assertForbidden();
    $this->delete("/admin/roles/{$role->id}")->assertForbidden();
});

test('roles in use cannot be deleted', function () {
    signIn(permissions: ['roles.manage']);
    $role = Role::create(['name' => 'ops', 'guard_name' => 'web']);
    User::factory()->create()->assignRole($role);

    $this->delete("/admin/roles/{$role->id}")->assertSessionHas('error');
    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

test('updating a role changes what its users can do immediately', function () {
    signIn(permissions: ['roles.manage']);
    $role = Role::create(['name' => 'ops', 'guard_name' => 'web']);
    $member = User::factory()->create();
    $member->assignRole($role);

    $this->put("/admin/roles/{$role->id}", ['name' => 'ops', 'permissions' => ['deals.view']])->assertRedirect();

    expect($member->fresh()->can('deals.view'))->toBeTrue();
});
