<?php

use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins'    => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com', 'analyst@test.com'],
        'db-governor.connections'       => ['main' => 'sqlite'],
        'db-governor.path'              => 'db-governor',
    ]);

    // Seed two queries by different users
    GovernedQuery::create([
        'connection'   => 'main',
        'sql_raw'      => 'UPDATE users SET active=0 WHERE id=1',
        'query_type'   => 'write',
        'risk_level'   => 'low',
        'status'       => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    GovernedQuery::create([
        'connection'   => 'main',
        'sql_raw'      => 'UPDATE orders SET status=1 WHERE id=2',
        'query_type'   => 'write',
        'risk_level'   => 'low',
        'status'       => QueryStatus::Pending->value,
        'submitted_by' => 'analyst@test.com',
    ]);
});

// ── employee scope ─────────────────────────────────────────────────────────

it('employee sees only their own queries', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('dev@test.com');
    $guard->setPayload($guard->validateToken($token));

    $queries = $this->get(route('db-governor.queries', [
        'token'      => $token,
        'connection' => 'main',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(1);
    expect($queries->first()->submitted_by)->toBe('dev@test.com');
});

it('employee cannot see another employee\'s queries', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('dev@test.com');
    $guard->setPayload($guard->validateToken($token));

    $queries = $this->get(route('db-governor.queries', [
        'token'      => $token,
        'connection' => 'main',
    ]))->assertOk()->viewData('queries');

    $submitters = $queries->pluck('submitted_by')->unique()->values()->all();
    expect($submitters)->not->toContain('analyst@test.com');
});

it('employee submitted_by filter param is ignored (always scoped to self)', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('dev@test.com');
    $guard->setPayload($guard->validateToken($token));

    // Even if an employee passes ?submitted_by=analyst, they only see their own
    $queries = $this->get(route('db-governor.queries', [
        'token'        => $token,
        'connection'   => 'main',
        'submitted_by' => 'analyst@test.com',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(1);
    expect($queries->first()->submitted_by)->toBe('dev@test.com');
});

// ── admin scope ────────────────────────────────────────────────────────────

it('admin sees all queries across all users', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($token));

    $queries = $this->get(route('db-governor.queries', [
        'token'      => $token,
        'connection' => 'main',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(2);
});

it('admin can filter queries by submitted_by email', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($token));

    $queries = $this->get(route('db-governor.queries', [
        'token'        => $token,
        'connection'   => 'main',
        'submitted_by' => 'analyst@test.com',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(1);
    expect($queries->first()->submitted_by)->toBe('analyst@test.com');
});

it('admin submitted_by filter with unknown email returns empty results', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($token));

    $queries = $this->get(route('db-governor.queries', [
        'token'        => $token,
        'connection'   => 'main',
        'submitted_by' => 'nobody@example.com',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(0);
});

// ── submitters list from config ─────────────────────────────────────────────

it('admin receives a submitters list built from config admins and employees', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($token));

    $submitters = $this->get(route('db-governor.queries', [
        'token'      => $token,
        'connection' => 'main',
    ]))->assertOk()->viewData('submitters');

    expect($submitters)
        ->toContain('admin@test.com')
        ->toContain('dev@test.com')
        ->toContain('analyst@test.com');
});

it('submitters list contains no duplicates', function () {
    // Add admin@test.com to both lists to verify deduplication
    config([
        'db-governor.allowed.admins'    => ['admin@test.com'],
        'db-governor.allowed.employees' => ['admin@test.com', 'dev@test.com'],
    ]);

    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($token));

    $submitters = $this->get(route('db-governor.queries', [
        'token'      => $token,
        'connection' => 'main',
    ]))->assertOk()->viewData('submitters');

    expect(count($submitters))->toBe(count(array_unique($submitters)));
});

it('employee receives an empty submitters list (no filter UI shown)', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('dev@test.com');
    $guard->setPayload($guard->validateToken($token));

    $submitters = $this->get(route('db-governor.queries', [
        'token'      => $token,
        'connection' => 'main',
    ]))->assertOk()->viewData('submitters');

    expect($submitters)->toBeEmpty();
});

// ── isAdmin flag passed to view ─────────────────────────────────────────────

it('isAdmin is true in view for admin user', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($token));

    $isAdmin = $this->get(route('db-governor.queries', [
        'token'      => $token,
        'connection' => 'main',
    ]))->assertOk()->viewData('isAdmin');

    expect($isAdmin)->toBeTrue();
});

it('isAdmin is false in view for employee user', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('dev@test.com');
    $guard->setPayload($guard->validateToken($token));

    $isAdmin = $this->get(route('db-governor.queries', [
        'token'      => $token,
        'connection' => 'main',
    ]))->assertOk()->viewData('isAdmin');

    expect($isAdmin)->toBeFalse();
});

