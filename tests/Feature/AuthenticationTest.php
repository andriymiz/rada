<?php

use App\Models\User;

test('dashboard requires authentication', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('inactive user cannot sign in', function () {
    $user = User::factory()->create([
        'email' => 'disabled@example.test',
        'password' => 'test-password-only',
        'is_active' => false,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'test-password-only',
    ])->assertSessionHasErrors('email')->assertRedirect();

    $this->assertGuest();
});
