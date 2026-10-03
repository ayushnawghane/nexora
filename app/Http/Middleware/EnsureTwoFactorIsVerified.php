<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every authenticated route requires 2FA: users without it are sent to set it up, users who
 * haven't entered a code this session are sent to the challenge.
 */
class EnsureTwoFactorIsVerified
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->guest(route('two-factor.setup'));
        }

        if (! $this->twoFactor->hasPassed($request->session())) {
            return redirect()->guest(route('two-factor.challenge'));
        }

        return $next($request);
    }
}
