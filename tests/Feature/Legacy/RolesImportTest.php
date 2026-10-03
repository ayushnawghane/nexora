<?php

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    legacySchema();
    legacyRows('users', [
        ['id' => 1, 'name' => 'Anita Ops', 'emp_code' => '101', 'email' => 'anita@b.test', 'password' => Hash::make('a')],
        ['id' => 2, 'name' => 'Vikram Accounts', 'emp_code' => '102', 'email' => 'vikram@b.test', 'password' => Hash::make('b')],
    ]);
    legacyRows('roles', [
        ['id' => 2, 'name' => 'Operations', 'slug' => 'operations'],
        ['id' => 3, 'name' => 'Accounts', 'slug' => 'accounts'],
        ['id' => 99, 'name' => 'super-admin', 'slug' => 'super-admin'],
    ]);
    legacyRows('permissions', [
        ['id' => 1, 'name' => 'View', 'slug' => 'transaction_view'],
        ['id' => 2, 'name' => 'View', 'slug' => 'dealdash'],
        ['id' => 3, 'name' => 'View', 'slug' => 'isin'],
    ]);
    legacyRows('roles_permissions', [['role_id' => 2, 'permission_id' => 1], ['role_id' => 2, 'permission_id' => 2], ['role_id' => 2, 'permission_id' => 3]]);
    legacyRows('users_roles', [['user_id' => 1, 'role_id' => 2], ['user_id' => 2, 'role_id' => 3], ['user_id' => 2, 'role_id' => 99]]);
    legacyRows('users_permissions', [['user_id' => 2, 'permission_id' => 1]]);
});

test('roles come over with their members and the permissions the map translates', function () {
    $this->artisan('legacy:import', ['area' => 'all'])
        ->expectsOutputToContain('Legacy permissions with no Nexora equivalent (not granted): 1')
        ->assertSuccessful();

    $operations = Role::query()->where('legacy_id', 2)->sole();
    expect($operations->name)->toBe('Operations')
        ->and($operations->permissions->pluck('name')->sort()->values()->all())->toBe(['deals.view', 'transactions.view']);

    $anita = User::query()->where('emp_code', '101')->sole();
    $vikram = User::query()->where('emp_code', '102')->sole();
    expect($anita->hasRole('Operations'))->toBeTrue()
        ->and($vikram->hasRole('Accounts'))->toBeTrue()
        ->and($vikram->getDirectPermissions()->pluck('name')->all())->toBe(['transactions.view']);

    // A legacy role can never become, or be merged into, the super-admin role.
    expect($vikram->hasRole(Permissions::superAdminRole()))->toBeFalse()
        ->and(Role::query()->where('name', Permissions::superAdminRole())->sole()->legacy_id)->toBeNull();

    $this->artisan('legacy:import', ['area' => 'roles'])->assertSuccessful();
    expect(Role::query()->count())->toBe(3);
});
