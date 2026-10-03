<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Str;

class SetUserPassword
{
    /**
     * @param  bool  $temporary  true when an administrator sets it: the user must change it at next login.
     */
    public function handle(User $user, string $password, bool $temporary = false): void
    {
        $user->forceFill([
            'password' => $password,
            'password_changed_at' => now(),
            'must_change_password' => $temporary,
            'remember_token' => Str::random(60),
        ])->save();
    }
}
