<?php

namespace App\Actions\Users;

use App\Actions\Auth\SetUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetUserSecurity
{
    public function __construct(private readonly SetUserPassword $setPassword) {}

    /** Sets a temporary password the user must change at next login, and signs them out everywhere. */
    public function resetPassword(User $user): string
    {
        $temporary = Str::password(14);

        DB::transaction(function () use ($user, $temporary) {
            $this->setPassword->handle($user, $temporary, temporary: true);
            $user->sessions()->delete();
        });

        activity()->performedOn($user)->event('password_reset')->log('Password reset by administrator');

        return $temporary;
    }

    /** Clears 2FA so the user sets it up again at next login. */
    public function resetTwoFactor(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
            $user->sessions()->delete();
        });

        activity()->performedOn($user)->event('two_factor_reset')->log('Two-factor authentication reset by administrator');
    }
}
