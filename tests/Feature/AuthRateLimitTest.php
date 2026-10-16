<?php

use App\Models\User;

test('sign-in, registration and recovery endpoints are rate limited', function (string $route, int $limit, array $payload) {
    User::factory()->create(['email' => 'target@example.com']);

    for ($attempt = 1; $attempt <= $limit; $attempt++) {
        expect($this->post(route($route), $payload)->status())->not->toBe(429);
    }

    $this->post(route($route), $payload)->assertStatus(429);
})->with([
    'password sign-in' => ['login.post', 10, ['login' => 'target@example.com', 'password' => 'wrong-password']],
    'registration' => ['register.post', 5, ['name' => 'x', 'email' => 'not-an-email', 'password' => 'x']],
    'passkey sign-in' => ['webauthn.login.verify', 10, ['id' => 'unknown']],
    'two-factor challenge' => ['2fa.verify', 10, ['code' => '000000']],
    'recovery request' => ['recovery.send', 5, ['email' => 'target@example.com']],
    'password reset' => ['recovery.reset', 10, ['token' => 'x', 'password' => 'new-password', 'password_confirmation' => 'new-password']],
]);
