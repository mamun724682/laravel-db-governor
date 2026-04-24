<?php

use Mamun724682\DbGovernor\Services\AccessGuard;

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => [],
        'db-governor.connections' => ['main' => 'sqlite'],
    ]);
});

it('dashboard shows stats cards', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('Executed');
});

it('dashboard shows all six stat labels', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('Approved')
        ->assertSee('Executed')
        ->assertSee('Rejected')
        ->assertSee('Blocked');
});

it('write modal markup is present on queries page', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $this->get(route('db-governor.queries', ['connection' => 'main']))
        ->assertOk()
        ->assertSee('writeModal', false)
        ->assertSee('Submit for Approval');
});
