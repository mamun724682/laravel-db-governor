<?php

use Illuminate\Support\Facades\Cache;
use Mamun724682\DbGovernor\Services\AccessGuard;

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
    ]);
});

it('shows the login page', function () {
    $this->get(route('db-governor.login'))
        ->assertOk()
        ->assertViewIs('db-governor::login');
});

it('redirects to connection picker on valid email', function () {
    $this->post(route('db-governor.login.submit'), ['email' => 'admin@test.com'])
        ->assertRedirect();
});

it('redirects back with error on unknown email', function () {
    $this->post(route('db-governor.login.submit'), ['email' => 'nobody@test.com'])
        ->assertRedirect(route('db-governor.login'))
        ->assertSessionHas('error');
});

it('auto-redirects to dashboard when only one connection', function () {
    $token = $this->loginAsGuard('admin@test.com');
    $this->get(route('db-governor.connections.pick'))
        ->assertRedirect();
});

// ── logout ────────────────────────────────────────────────────────────────

it('logout clears the cache token and redirects to login', function () {
    $token = $this->loginAsGuard('admin@test.com');

    expect(Cache::has('dbg_token_'.$token))->toBeTrue();

    $this->post(route('db-governor.logout'))
        ->assertRedirect(route('db-governor.login'))
        ->assertSessionHas('success');

    expect(Cache::has('dbg_token_'.$token))->toBeFalse();
});

it('token is invalid after logout', function () {
    $guard = app(AccessGuard::class);
    $token = $this->loginAsGuard('admin@test.com');

    $this->post(route('db-governor.logout'));

    expect(fn () => $guard->validateToken($token))
        ->toThrow(RuntimeException::class);
});

it('logout redirects to login even with an already-invalid token', function () {
    $this->post(route('db-governor.logout'))
        ->assertRedirect(route('db-governor.login'));
});
