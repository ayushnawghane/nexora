<?php

namespace App\Http\Requests\Auth;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'emp_code' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['emp_code' => 'employee code'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['emp_code' => Str::upper(trim((string) $this->input('emp_code')))]);
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = ['emp_code' => $this->string('emp_code')->toString(), 'password' => $this->string('password')->toString()];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            $this->record(false, LoginEvent::REASON_INVALID_CREDENTIALS, User::query()->where('emp_code', $credentials['emp_code'])->first());

            throw ValidationException::withMessages(['emp_code' => trans('auth.failed')]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $this->record(false, LoginEvent::REASON_INACTIVE, $user);

            throw ValidationException::withMessages(['emp_code' => 'This account has been deactivated. Contact your administrator.']);
        }

        RateLimiter::clear($this->throttleKey());
        $this->record(true, null, $user);

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $this->ip()])->save();
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));
        $this->record(false, LoginEvent::REASON_THROTTLED, null);

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'emp_code' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate($this->string('emp_code')->lower()->toString().'|'.$this->ip());
    }

    private function record(bool $successful, ?string $reason, ?User $user): void
    {
        LoginEvent::query()->create([
            'user_id' => $user?->id,
            'emp_code' => $this->string('emp_code')->limit(50, '')->toString(),
            'successful' => $successful,
            'reason' => $reason,
            'ip_address' => $this->ip(),
            'user_agent' => Str::limit((string) $this->userAgent(), 500, ''),
        ]);
    }
}
