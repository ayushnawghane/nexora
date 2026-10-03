<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('users who must change their password are sent to the change page', function () {
    signIn(User::factory()->mustChangePassword()->create());

    $this->get('/dashboard')->assertRedirect(route('password.change'));
    $this->get('/password/change')->assertOk();
});

test('expired passwords must be changed', function () {
    signIn(User::factory()->passwordExpired()->create());

    $this->get('/dashboard')->assertRedirect(route('password.change'));
});

test('password can be updated', function () {
    $user = signIn(User::factory()->mustChangePassword()->create());

    $this->put('/password', [
        'current_password' => 'password',
        'password' => 'N3xora!Secure#2026',
        'password_confirmation' => 'N3xora!Secure#2026',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    expect(Hash::check('N3xora!Secure#2026', $user->password))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and($user->password_changed_at->isToday())->toBeTrue();

    $this->get('/dashboard')->assertOk();
});

test('the current password is required', function () {
    signIn();

    $this->put('/password', [
        'current_password' => 'wrong-password',
        'password' => 'N3xora!Secure#2026',
        'password_confirmation' => 'N3xora!Secure#2026',
    ])->assertSessionHasErrors('current_password');
});

test('weak passwords are rejected', function (string $weak) {
    signIn();

    $this->put('/password', [
        'current_password' => 'password',
        'password' => $weak,
        'password_confirmation' => $weak,
    ])->assertSessionHasErrors('password');
})->with(['short1!A', 'alllowercase1!', 'ALLUPPERCASE1!', 'NoNumbers!!!', 'NoSymbols1234']);

test('the new password must differ from the current one', function () {
    $user = User::factory()->create(['password' => 'N3xora!Secure#2026']);
    signIn($user);

    $this->put('/password', [
        'current_password' => 'N3xora!Secure#2026',
        'password' => 'N3xora!Secure#2026',
        'password_confirmation' => 'N3xora!Secure#2026',
    ])->assertSessionHasErrors('password');
});
