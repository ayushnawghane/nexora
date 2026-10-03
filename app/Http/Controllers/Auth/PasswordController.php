<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\SetUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /** Forced change: first login, after an admin reset, or when the password has expired. */
    public function edit(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->passwordNeedsChange()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/ChangePassword', [
            'reason' => $user->must_change_password ? 'first_login' : 'expired',
            'maxAgeDays' => User::PASSWORD_MAX_AGE_DAYS,
        ]);
    }

    public function update(UpdatePasswordRequest $request, SetUserPassword $setPassword): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $setPassword->handle($user, $request->string('password')->toString());

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('success', 'Password updated.');
    }
}
