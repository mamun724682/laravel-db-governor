<?php

use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
    ]);
});

it('queries page lists governed queries with status badges', function () {
    GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE users SET x=1',
        'query_type' => 'write',
        'name' => 'My Query',
        'risk_level' => 'high',
        'status' => 'pending',
        'submitted_by' => 'dev@test.com',
    ]);

    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');

    $this->get(route('db-governor.queries', ['token' => $token, 'connection' => 'main']))
        ->assertOk()
        ->assertSee('My Query')
        ->assertSee('PENDING');
});

it('queries page extends layout', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');

    $this->get(route('db-governor.queries', ['token' => $token, 'connection' => 'main']))
        ->assertOk()
        ->assertSee('DB Governance');
});

it('queries page shows action modal markup for admin', function () {
    GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE orders SET status=1',
        'query_type' => 'write',
        'name' => 'Order Update',
        'risk_level' => 'high',
        'status' => 'pending',
        'submitted_by' => 'dev@test.com',
    ]);

    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');

    $this->get(route('db-governor.queries', ['token' => $token, 'connection' => 'main']))
        ->assertOk()
        ->assertSee('Approve', false)
        ->assertSee('Reject', false);
});
