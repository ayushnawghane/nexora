<?php

use App\Models\User;
use App\Services\Auth\TwoFactor;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

require __DIR__.'/Support/transactions.php';
require __DIR__.'/Support/deals.php';

/**
 * Signs in a fully set-up user (2FA confirmed and passed this session, fresh password),
 * optionally granting permissions.
 *
 * @param  list<string>  $permissions
 */
function signIn(?User $user = null, array $permissions = []): User
{
    $user ??= User::factory()->create();

    if ($permissions !== []) {
        test()->seed(PermissionSeeder::class);
        $user->givePermissionTo($permissions);
    }

    test()->actingAs($user)->withSession([TwoFactor::SESSION_PASSED_AT => now()->getTimestamp()]);

    return $user;
}
