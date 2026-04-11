<?php

it('login view contains email input', function () {
    $this->get(route('db-governor.login'))
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('db-governor.login.submit', false);
});

it('login view shows flash error when present', function () {
    $this->withSession(['error' => 'Email not found'])
        ->get(route('db-governor.login'))
        ->assertSee('Email not found');
});

it('connections view lists all connections', function () {
    config([
        'db-governor.allowed.admins' => ['admin@example.com'],
        'db-governor.connections'    => [
            'primary'   => 'sqlite',
            'secondary' => 'sqlite',
        ],
    ]);

    $guard = app(\Mamun724682\DbGovernor\Services\AccessGuard::class);
    $token = $guard->login('admin@example.com');

    $this->get(route('db-governor.connections.pick', ['token' => $token]))
        ->assertOk()
        ->assertSee('primary', false)
        ->assertSee('secondary', false)
        ->assertSee('localStorage', false);
});

