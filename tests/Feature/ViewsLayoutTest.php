<?php

use Mamun724682\DbGovernor\Services\AccessGuard;

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => [],
        'db-governor.connections' => ['main' => 'sqlite'],
    ]);
});

it('dashboard view extends layout and contains key structure', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('DB Governance');
});

it('layout contains connection switcher', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('main', false);
});

it('layout shows email and role of current user', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('admin@test.com')
        ->assertSee('admin');
});
