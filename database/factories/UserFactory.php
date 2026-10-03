<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Default state is a fully set-up user: active, fresh password, 2FA confirmed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'emp_code' => fake()->unique()->numerify('E####'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'password_changed_at' => now(),
            'must_change_password' => false,
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function withoutTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }

    public function passwordExpired(): static
    {
        return $this->state(fn () => [
            'password_changed_at' => now()->subDays(User::PASSWORD_MAX_AGE_DAYS + 1),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
