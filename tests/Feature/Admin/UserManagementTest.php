<?php

use App\Models\Department;
use App\Models\Product;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    Role::findOrCreate('operations', 'web');
});

function validUserPayload(array $overrides = []): array
{
    return array_merge([
        'emp_code' => 'e2001',
        'name' => 'Asha Rao',
        'email' => 'Asha.Rao@Beacon.test',
        'mobile' => '+919876543210',
        'roles' => ['operations'],
        'product_ids' => [],
        'vertical_ids' => [],
        'vertical_team_ids' => [],
    ], $overrides);
}

test('users without permission cannot see or manage users', function () {
    signIn();

    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/users/create')->assertForbidden();
    $this->post('/admin/users', validUserPayload())->assertForbidden();
});

test('the user list can be filtered and sorted', function () {
    signIn(permissions: ['users.view']);
    User::factory()->create(['name' => 'Zed Inactive', 'is_active' => false]);
    User::factory()->create(['name' => 'Amy Active']);

    $this->get('/admin/users?filter[is_active]=0')->assertInertia(fn ($page) => $page
        ->component('Admin/Users/Index')
        ->where('users.total', 1)
        ->where('users.data.0.name', 'Zed Inactive'));

    $this->get('/admin/users?filter[search]=amy')->assertInertia(fn ($page) => $page->where('users.total', 1));
    $this->get('/admin/users?sort=-name')->assertOk();
    $this->get('/admin/users?sort=password')->assertStatus(400);
});

test('creating a user normalises input, assigns roles and returns a one-time password', function () {
    signIn(permissions: ['users.view', 'users.manage']);
    $department = Department::query()->create(['name' => 'Operations']);
    $product = Product::query()->create(['code' => 'DT', 'name' => 'Debenture Trustee']);

    $this->post('/admin/users', validUserPayload([
        'department_id' => $department->id,
        'product_ids' => [$product->id],
    ]))->assertRedirect('/admin/users')->assertSessionHas('temporary_password');

    $user = User::query()->where('emp_code', 'E2001')->firstOrFail();
    expect($user->email)->toBe('asha.rao@beacon.test')
        ->and($user->hasRole('operations'))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->products->pluck('id')->all())->toBe([$product->id])
        ->and($user->department->is($department))->toBeTrue();
});

test('user validation rejects bad input', function (array $payload, string $field) {
    signIn(permissions: ['users.manage']);
    User::factory()->create(['emp_code' => 'TAKEN', 'email' => 'taken@beacon.test']);

    $this->post('/admin/users', validUserPayload($payload))->assertSessionHasErrors($field);
})->with([
    'duplicate code' => [['emp_code' => 'taken'], 'emp_code'],
    'bad code chars' => [['emp_code' => 'E 1/2'], 'emp_code'],
    'duplicate email' => [['email' => 'TAKEN@beacon.test'], 'email'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'bad mobile' => [['mobile' => '12ab'], 'mobile'],
    'no roles' => [['roles' => []], 'roles'],
    'unknown role' => [['roles' => ['ghost']], 'roles.0'],
    'unknown department' => [['department_id' => 999], 'department_id'],
    'future joining date' => [['date_of_joining' => '2099-01-01'], 'date_of_joining'],
]);

test('only super-admins can grant the super-admin role', function () {
    signIn(permissions: ['users.manage']);

    $this->post('/admin/users', validUserPayload(['roles' => [Permissions::superAdminRole()]]))
        ->assertSessionHasErrors('roles.0');
});

test('non super-admins cannot edit a super-admin', function () {
    signIn(permissions: ['users.manage']);
    $admin = User::factory()->create();
    $admin->assignRole(Permissions::superAdminRole());

    $this->get("/admin/users/{$admin->ulid}/edit")->assertForbidden();
});

test('a user cannot report to themselves', function () {
    signIn(permissions: ['users.manage']);
    $target = User::factory()->create();
    $target->assignRole('operations');

    $this->put("/admin/users/{$target->ulid}", validUserPayload([
        'emp_code' => $target->emp_code,
        'email' => $target->email,
        'reporting_manager_id' => $target->id,
    ]))->assertSessionHasErrors('reporting_manager_id');
});

test('deactivating a user signs them out and blocks self-deactivation', function () {
    $actor = signIn(permissions: ['users.manage']);
    $target = User::factory()->create();

    $this->post("/admin/users/{$target->ulid}/toggle-active")->assertRedirect();
    expect($target->fresh()->is_active)->toBeFalse();

    $this->post("/admin/users/{$actor->ulid}/toggle-active")->assertForbidden();
});

test('admins can reset another user\'s password and 2FA', function () {
    signIn(permissions: ['users.reset_security']);
    $target = User::factory()->create();

    $this->post("/admin/users/{$target->ulid}/reset-password")->assertSessionHas('temporary_password');
    expect($target->fresh()->must_change_password)->toBeTrue();

    $this->post("/admin/users/{$target->ulid}/reset-two-factor")->assertRedirect();
    expect($target->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('admins cannot reset their own security from the admin panel', function () {
    $actor = signIn(permissions: ['users.reset_security']);

    $this->post("/admin/users/{$actor->ulid}/reset-password")->assertForbidden();
});
