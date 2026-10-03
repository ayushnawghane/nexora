<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Auth\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function setup(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.challenge');
        }

        $secret = $request->session()->get(TwoFactor::SESSION_PENDING_SECRET);

        if (! is_string($secret)) {
            $secret = $this->twoFactor->generateSecret();
            $request->session()->put(TwoFactor::SESSION_PENDING_SECRET, $secret);
        }

        return Inertia::render('Auth/TwoFactorSetup', [
            'qrCodeSvg' => $this->twoFactor->qrCodeSvg($user, $secret),
            'secret' => trim(chunk_split($secret, 4, ' ')),
        ]);
    }

    public function confirm(TwoFactorCodeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $secret = $request->session()->get(TwoFactor::SESSION_PENDING_SECRET);

        abort_if($user->hasTwoFactorEnabled() || ! is_string($secret), 409, 'Two-factor authentication is already set up.');

        $this->throttle($request, $user);

        if (! $this->twoFactor->verify($user, $secret, $request->string('code')->toString())) {
            $this->fail($request, $user);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget(TwoFactor::SESSION_PENDING_SECRET);
        $this->twoFactor->markPassed($request->session());
        RateLimiter::clear($this->throttleKey($user));

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('success', 'Two-factor authentication is set up.');
    }

    public function challenge(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'reconfirm' => $request->boolean('reconfirm'),
        ]);
    }

    public function verify(TwoFactorCodeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->hasTwoFactorEnabled(), 409, 'Two-factor authentication is not set up.');

        $this->throttle($request, $user);

        if (! $this->twoFactor->verify($user, (string) $user->two_factor_secret, $request->string('code')->toString())) {
            $this->fail($request, $user);
        }

        $this->twoFactor->markPassed($request->session());
        RateLimiter::clear($this->throttleKey($user));

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function throttle(Request $request, User $user): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($user), self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($user));

            throw ValidationException::withMessages([
                'code' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }
    }

    private function fail(Request $request, User $user): never
    {
        RateLimiter::hit($this->throttleKey($user));

        LoginEvent::query()->create([
            'user_id' => $user->id,
            'emp_code' => $user->emp_code,
            'successful' => false,
            'reason' => LoginEvent::REASON_TWO_FACTOR_FAILED,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        throw ValidationException::withMessages(['code' => 'That code is not valid. Try the current code from your app.']);
    }

    private function throttleKey(User $user): string
    {
        return 'two-factor:'.$user->getKey();
    }
}
