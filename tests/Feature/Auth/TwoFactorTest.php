<?php

use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Auth\TwoFactor;
use PragmaRX\Google2FA\Google2FA;

test('users without 2FA are sent to set it up before anything else', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('two-factor.setup'));
    $this->actingAs($user)->get('/profile')->assertRedirect(route('two-factor.setup'));
});

test('users with 2FA must enter a code each session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('two-factor.challenge'));
});

test('2FA setup confirms with a valid code', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)->get('/two-factor/setup')->assertOk();
    $secret = session(TwoFactor::SESSION_PENDING_SECRET);

    $code = (new Google2FA)->getCurrentOtp($secret);

    $this->actingAs($user)->post('/two-factor/setup', ['code' => $code])
        ->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    expect($user->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->two_factor_secret)->toBe($secret);

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('2FA setup rejects a wrong code', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $this->actingAs($user)->get('/two-factor/setup');
    $this->actingAs($user)->post('/two-factor/setup', ['code' => '000000'])->assertSessionHasErrors('code');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

test('the challenge accepts the current code', function () {
    $user = User::factory()->create();
    $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);

    $this->actingAs($user)->post('/two-factor/challenge', ['code' => $code])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->actingAs($user)->get('/dashboard')->assertOk();
});

test('the challenge rejects wrong codes and logs them', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/two-factor/challenge', ['code' => '123456'])->assertSessionHasErrors('code');

    expect(LoginEvent::query()->where('reason', LoginEvent::REASON_TWO_FACTOR_FAILED)->count())->toBe(1);
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('two-factor.challenge'));
});

test('a code cannot be used twice', function () {
    $user = User::factory()->create();
    $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);

    $this->actingAs($user)->post('/two-factor/challenge', ['code' => $code])->assertSessionHasNoErrors();

    $this->flushSession();
    $this->actingAs($user)->post('/two-factor/challenge', ['code' => $code])->assertSessionHasErrors('code');
});

test('codes must be six digits', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/two-factor/challenge', ['code' => 'abc'])->assertSessionHasErrors('code');
});
