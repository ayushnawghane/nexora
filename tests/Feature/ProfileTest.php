<?php

use App\Models\User;

test('profile page is displayed', function () {
    signIn();

    $this->get('/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = signIn();

    $this->patch('/profile', ['name' => 'Test User', 'email' => 'test@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com');
});

test('email must stay unique', function () {
    $other = User::factory()->create();
    signIn();

    $this->patch('/profile', ['name' => 'Test User', 'email' => $other->email])
        ->assertSessionHasErrors('email');
});

test('users cannot delete their own account', function () {
    $user = signIn();

    $this->delete('/profile')->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});

test('theme preference is saved', function () {
    $user = signIn();

    $this->patch('/preferences/theme', ['theme' => 'light'])->assertRedirect();
    expect($user->fresh()->theme)->toBe('light');

    $this->patch('/preferences/theme', ['theme' => 'neon'])->assertSessionHasErrors('theme');
});

test('only safe user fields are shared with the frontend', function () {
    signIn();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page
        ->has('auth.user', fn ($user) => $user
            ->hasAll(['id', 'emp_code', 'name', 'email', 'theme'])
            ->missing('password')
            ->missing('two_factor_secret')
        )
    );
});
