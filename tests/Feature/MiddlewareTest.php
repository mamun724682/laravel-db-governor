<?php

use Illuminate\Support\Facades\Crypt;
use Mamun724682\DbGovernor\Services\AccessGuard;

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
    ]);
});

it('redirects to login when no token present', function () {
    $this->get('/db-governor/invalid-token')
        ->assertRedirect(route('db-governor.login'));
});

it('redirects to login when token is invalid', function () {
    $this->get('/db-governor/bad-token')
        ->assertRedirect(route('db-governor.login'));
});

it('redirects to login when token is expired', function () {
    $expired = Crypt::encryptString(json_encode([
        'email' => 'admin@test.com',
        'role' => 'admin',
        'expires_at' => now()->subHour()->toISOString(),
    ]));
    $this->get("/db-governor/{$expired}")
        ->assertRedirect(route('db-governor.login'));
});

it('returns 404 for unknown connection key', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $this->get("/db-governor/{$token}/nonexistent/")
        ->assertStatus(404);
});

it('allows valid token and known connection through', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $this->get("/db-governor/{$token}/main/")
        ->assertSuccessful();
});
