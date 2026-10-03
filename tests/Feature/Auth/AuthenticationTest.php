<?php

use App\Models\LoginEvent;
use App\Models\User;

test('login screen can be rendered', function () {
    $this->get('/login')->assertOk();
});

test('users sign in with employee code and password', function () {
    $user = User::factory()->create(['emp_code' => 'E1001']);

    $this->post('/login', ['emp_code' => 'e1001 ', 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(LoginEvent::query()->where('user_id', $user->id)->where('successful', true)->exists())->toBeTrue()
        ->and($user->fresh()->last_login_at)->not->toBeNull();
});

test('a wrong password is rejected and logged', function () {
    $user = User::factory()->create();

    $this->post('/login', ['emp_code' => $user->emp_code, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('emp_code');

    $this->assertGuest();
    expect(LoginEvent::query()->where('reason', LoginEvent::REASON_INVALID_CREDENTIALS)->count())->toBe(1);
});

test('deactivated users cannot sign in', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', ['emp_code' => $user->emp_code, 'password' => 'password'])
        ->assertSessionHasErrors('emp_code');

    $this->assertGuest();
    expect(LoginEvent::query()->where('reason', LoginEvent::REASON_INACTIVE)->exists())->toBeTrue();
});

test('login is locked after repeated failures', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['emp_code' => $user->emp_code, 'password' => 'wrong']);
    }

    $this->post('/login', ['emp_code' => $user->emp_code, 'password' => 'password'])
        ->assertSessionHasErrors('emp_code');

    $this->assertGuest();
});

test('email addresses are not accepted as the login', function () {
    $user = User::factory()->create();

    $this->post('/login', ['emp_code' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('emp_code');

    $this->assertGuest();
});

test('users can sign out', function () {
    signIn();

    $this->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});
