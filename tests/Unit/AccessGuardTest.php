<?php

use Illuminate\Support\Facades\Cache;
use Mamun724682\DbGovernor\Services\AccessGuard;

beforeEach(function () {
    config([
        'db-governor.allowed.admins'     => ['admin@company.com', 'lead@company.com'],
        'db-governor.allowed.employees'  => ['dev@company.com', 'analyst@company.com'],
        'db-governor.token_expiry_hours' => 8,
    ]);
    $this->guard = new AccessGuard();
});

// ── login ──────────────────────────────────────────────────────────────────

it('login returns a 32-char token for a valid admin email', function () {
    $token = $this->guard->login('admin@company.com');
    expect($token)->toBeString()->toHaveLength(32);
});

it('login returns a 32-char token for a valid employee email', function () {
    $token = $this->guard->login('dev@company.com');
    expect($token)->toBeString()->toHaveLength(32);
});

it('login returns null for an unknown email', function () {
    expect($this->guard->login('stranger@example.com'))->toBeNull();
});

it('login is case-insensitive', function () {
    expect($this->guard->login('Admin@Company.COM'))->not->toBeNull();
});

// ── cache storage ──────────────────────────────────────────────────────────

it('login stores token payload in cache', function () {
    $token = $this->guard->login('admin@company.com');
    $data  = Cache::get('dbg_token_'.$token);

    expect($data)->toBeArray()
        ->toHaveKey('email', 'admin@company.com')
        ->toHaveKey('role', 'admin')
        ->toHaveKey('expires_at');
});

it('login stores employee role in cache', function () {
    $token = $this->guard->login('dev@company.com');
    $data  = Cache::get('dbg_token_'.$token);

    expect($data['role'])->toBe('employee');
});

// ── validateToken ──────────────────────────────────────────────────────────

it('validateToken returns correct payload for admin', function () {
    $token   = $this->guard->login('admin@company.com');
    $payload = $this->guard->validateToken($token);

    expect($payload['email'])->toBe('admin@company.com');
    expect($payload['role'])->toBe('admin');
});

it('validateToken returns correct payload for employee', function () {
    $token   = $this->guard->login('dev@company.com');
    $payload = $this->guard->validateToken($token);

    expect($payload['role'])->toBe('employee');
});

it('validateToken throws on unknown (non-existent) token', function () {
    expect(fn () => $this->guard->validateToken('totally-unknown-token-xyz'))
        ->toThrow(\RuntimeException::class);
});

it('validateToken throws on expired token payload in cache', function () {
    $fakeToken = 'fake_expired_token_abc123xyz456';
    Cache::put('dbg_token_'.$fakeToken, [
        'email'      => 'admin@company.com',
        'role'       => 'admin',
        'expires_at' => now()->subHour()->toISOString(),
    ]);

    expect(fn () => $this->guard->validateToken($fakeToken))
        ->toThrow(\RuntimeException::class, 'expired');
});

it('validateToken throws when email no longer in config', function () {
    $token = $this->guard->login('dev@company.com');
    config(['db-governor.allowed.employees' => []]);

    expect(fn () => $this->guard->validateToken($token))
        ->toThrow(\RuntimeException::class, 'authorized');
});

it('email in both admin and employee lists is treated as admin', function () {
    config([
        'db-governor.allowed.admins'    => ['both@company.com'],
        'db-governor.allowed.employees' => ['both@company.com'],
    ]);
    $token   = $this->guard->login('both@company.com');
    $payload = $this->guard->validateToken($token);

    expect($payload['role'])->toBe('admin');
});

// ── revokeToken ────────────────────────────────────────────────────────────

it('revokeToken removes token from cache', function () {
    $token = $this->guard->login('admin@company.com');
    expect(Cache::has('dbg_token_'.$token))->toBeTrue();

    $this->guard->revokeToken($token);
    expect(Cache::has('dbg_token_'.$token))->toBeFalse();
});

it('validateToken throws after revokeToken', function () {
    $token = $this->guard->login('admin@company.com');
    $this->guard->revokeToken($token);

    expect(fn () => $this->guard->validateToken($token))
        ->toThrow(\RuntimeException::class);
});

// ── accessors ─────────────────────────────────────────────────────────────

it('setPayload and accessors work correctly', function () {
    $this->guard->setPayload([
        'email'      => 'admin@company.com',
        'role'       => 'admin',
        'expires_at' => now()->addHour()->toISOString(),
    ]);

    expect($this->guard->email())->toBe('admin@company.com');
    expect($this->guard->role())->toBe('admin');
    expect($this->guard->isAdmin())->toBeTrue();
});

it('assertAdmin aborts 403 for employee role', function () {
    $this->guard->setPayload([
        'email'      => 'dev@company.com',
        'role'       => 'employee',
        'expires_at' => now()->addHour()->toISOString(),
    ]);

    expect(fn () => $this->guard->assertAdmin())
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

