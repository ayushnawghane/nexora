<?php

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Finder\Finder;

test('the seeder syncs every declared permission and removes stale ones', function () {
    Permission::findOrCreate('legacy.leftover', 'web');

    $this->seed(PermissionSeeder::class);

    expect(Permission::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Permissions::all())->sort()->values()->all());
});

test('super-admins pass every permission check', function () {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Permissions::superAdminRole());

    foreach (Permissions::all() as $permission) {
        expect($user->can($permission))->toBeTrue();
    }
});

test('the frontend receives the signed-in user\'s permissions', function () {
    signIn(permissions: ['users.view']);

    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('auth.permissions', ['users.view']));
});

test('code only references declared permissions', function () {
    $declared = Permissions::all();
    $undeclared = [];

    $files = Finder::create()->files()
        ->in([base_path('app'), base_path('routes'), base_path('resources/js')])
        ->name(['*.php', '*.jsx', '*.js']);

    foreach ($files as $file) {
        preg_match_all("/(?:can|permission|Permission|middleware)\\W{1,4}['\"]([a-z_]+(?:\\.[a-z_]+)+)['\"]/", $file->getContents(), $matches);
        foreach ($matches[1] as $name) {
            if (! in_array($name, $declared, true)) {
                $undeclared[] = $file->getRelativePathname().': '.$name;
            }
        }
    }

    expect($undeclared)->toBeEmpty();
});
