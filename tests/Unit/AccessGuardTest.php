<?php

use Mamun724682\DbGovernor\Services\AccessGuard;

beforeEach(function () {
    config([
        'db-governor.allowed.admins'     => ['admin@company.com', 'lead@company.com'],
        'db-governor.allowed.employees'  => ['dev@company.com', 'analyst@company.com'],
        'db-governor.token_expiry_hours' => 8,
    ]);
    $this->guard = new AccessGuard();
});

it('login returns a token for a valid admin email', function () {
    $token = $this->guard->login('admin@company.com');
    expect($token)->toBeString()->not->toBeEmpty();
});

it('login returns a token for a valid employee email', function () {
    $token = $this->guard->login('dev@company.com');
    expect($token)->toBeString()->not->toBeEmpty();
});

it('login returns null for an unknown email', function () {
    expect($this->guard->login('stranger@example.com'))->toBeNull();
});

it('login is case-insensitive', function () {
    expect($this->guard->login('Admin@Company.COM'))->not->toBeNull();
});

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

it('validateToken throws on expired token', function () {
    $expired = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
        'email'      => 'admin@company.com',
        'role'       => 'admin',
        'expires_at' => now()->subHour()->toISOString(),
    ]));
    expect(fn () => $this->guard->validateToken($expired))->toThrow(\RuntimeException::class, 'expired');
});

it('validateToken throws on tampered token', function () {
    expect(fn () => $this->guard->validateToken('not-a-valid-token'))->toThrow(\RuntimeException::class);
});

it('validateToken throws when email no longer in config', function () {
    $token = $this->guard->login('dev@company.com');
    config(['db-governor.allowed.employees' => []]);
    expect(fn () => $this->guard->validateToken($token))->toThrow(\RuntimeException::class, 'authorized');
});

it('email in both lists is treated as admin', function () {
    config([
        'db-governor.allowed.admins'    => ['both@company.com'],
        'db-governor.allowed.employees' => ['both@company.com'],
    ]);
    $token   = $this->guard->login('both@company.com');
    $payload = $this->guard->validateToken($token);
    expect($payload['role'])->toBe('admin');
});

it('setPayload and email/role/isAdmin accessors work', function () {
    $this->guard->setPayload(['email' => 'admin@company.com', 'role' => 'admin', 'expires_at' => now()->addHour()->toISOString()]);
    expect($this->guard->email())->toBe('admin@company.com');
    expect($this->guard->role())->toBe('admin');
    expect($this->guard->isAdmin())->toBeTrue();
});

it('assertAdmin aborts 403 for non-admin', function () {
    $this->guard->setPayload(['email' => 'dev@company.com', 'role' => 'employee', 'expires_at' => now()->addHour()->toISOString()]);
    expect(fn () => $this->guard->assertAdmin())->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

