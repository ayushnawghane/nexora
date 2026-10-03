<?php

namespace App\Http\Middleware;

use App\Services\Auth\TwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sensitive areas (God Mode) need a 2FA code entered within the last N minutes. */
class RequireRecentTwoFactor
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function handle(Request $request, Closure $next, string $minutes = '15'): Response
    {
        if (! $this->twoFactor->passedWithin($request->session(), (int) $minutes)) {
            return redirect()->guest(route('two-factor.challenge', ['reconfirm' => 1]));
        }

        return $next($request);
    }
}
