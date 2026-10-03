<?php

/*
 * Shared helpers for deal workspace tests.
 */

use App\Enums\StatusApprovalTeam;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/** Signs in a user who can see deals, plus any extra permissions. */
function dealUser(array $extra = []): User
{
    return signIn(permissions: ['deals.view', ...$extra]);
}

/** An active user who approves deal status changes for one team (or both). */
function statusApprover(StatusApprovalTeam ...$teams): User
{
    test()->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo(['deals.view', ...array_map(fn (StatusApprovalTeam $t) => $t->permission(), $teams)]);

    return $user;
}
