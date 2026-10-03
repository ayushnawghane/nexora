<?php

/*
 * Shared helpers for God Mode tests.
 */

use App\GodMode\Editors;
use App\Models\User;
use App\Services\Auth\TwoFactor;
use App\Support\Permissions;

/** A super-admin who entered a 2FA code just now. Needs PermissionSeeder to have run. */
function godUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Permissions::superAdminRole());
    test()->actingAs($user)->withSession([TwoFactor::SESSION_PASSED_AT => now()->getTimestamp()]);

    return $user;
}

/** The current values and fingerprint God Mode would show for a record. */
function godForm(string $editor, string $id): array
{
    $handler = Editors::get($editor);
    $record = $handler->find($id);

    return ['values' => $handler->values($record), 'fingerprint' => $handler->fingerprint($record)];
}
