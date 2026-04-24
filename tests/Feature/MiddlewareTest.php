<?php

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
    // Access protected route with no session token
    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertRedirect(route('db-governor.login'));
});

it('redirects to login when token is invalid', function () {
    $this->withSession(['dbg_token' => 'invalid-token'])
        ->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertRedirect(route('db-governor.login'));
});

it('redirects to login when token is expired', function () {
    // Put an expired token in the cache manually
    $expiredToken = 'expiredtoken1234567890123456789012';
    \Illuminate\Support\Facades\Cache::put('dbg_token_'.$expiredToken, [
        'email' => 'admin@test.com',
        'role' => 'admin',
        'expires_at' => now()->subHour()->toISOString(),
    ], now()->addMinute());

    $this->withSession(['dbg_token' => $expiredToken])
        ->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertRedirect(route('db-governor.login'));
});

it('redirects to connections picker for unknown connection key', function () {
    $this->loginAsGuard('admin@test.com');
    $this->get(route('db-governor.dashboard', ['connection' => 'nonexistent']))
        ->assertRedirect(route('db-governor.connections.pick'));
});

it('allows valid token and known connection through', function () {
    $this->loginAsGuard('admin@test.com');
    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertSuccessful();
});
