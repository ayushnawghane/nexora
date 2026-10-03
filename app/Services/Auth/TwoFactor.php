<?php

namespace App\Services\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class TwoFactor
{
    public const SESSION_PASSED_AT = 'two_factor.passed_at';

    public const SESSION_PENDING_SECRET = 'two_factor.pending_secret';

    /** Codes are valid for one 30-second step either side, to allow for clock drift. */
    private const WINDOW = 1;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('app.name'), $user->emp_code.' ('.$user->email.')', $secret);

        $writer = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    /**
     * Verifies a code and rejects replays: a code (time step) that already succeeded for this user
     * can't be used again.
     */
    public function verify(User $user, string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $cacheKey = 'two-factor:last-step:'.$user->getKey();
        $lastStep = Cache::get($cacheKey);

        $step = $this->google2fa->verifyKeyNewer($secret, $code, is_int($lastStep) ? $lastStep : null, self::WINDOW);

        if ($step === false) {
            return false;
        }

        Cache::put($cacheKey, $step === true ? $this->google2fa->getTimestamp() : $step, now()->addMinutes(5));

        return true;
    }

    public function markPassed(Session $session): void
    {
        $session->put(self::SESSION_PASSED_AT, now()->getTimestamp());
    }

    public function hasPassed(Session $session): bool
    {
        return is_int($session->get(self::SESSION_PASSED_AT));
    }

    public function passedWithin(Session $session, int $minutes): bool
    {
        $passedAt = $session->get(self::SESSION_PASSED_AT);

        return is_int($passedAt) && $passedAt >= now()->subMinutes($minutes)->getTimestamp();
    }
}
