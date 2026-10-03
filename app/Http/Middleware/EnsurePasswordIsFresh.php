<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Forces a password change on first login, after an admin reset, or once the password expires. */
class EnsurePasswordIsFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null && $user->passwordNeedsChange()) {
            return redirect()->guest(route('password.change'));
        }

        return $next($request);
    }
}
