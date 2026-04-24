<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com', 'analyst@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
    ]);

    // Seed two queries by different users
    GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE users SET active=0 WHERE id=1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE orders SET status=1 WHERE id=2',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'analyst@test.com',
    ]);
});

// ── employee scope ─────────────────────────────────────────────────────────

it('employee sees only their own queries', function () {
    $token = $this->loginAsGuard('dev@test.com');

    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(1);
    expect($queries->first()->submitted_by)->toBe('dev@test.com');
});

it('employee cannot see another employee\'s queries', function () {
    $token = $this->loginAsGuard('dev@test.com');

    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('queries');

    $submitters = $queries->pluck('submitted_by')->unique()->values()->all();
    expect($submitters)->not->toContain('analyst@test.com');
});

it('employee submitted_by filter param is ignored (always scoped to self)', function () {
    $token = $this->loginAsGuard('dev@test.com');

    // Even if an employee passes ?submitted_by=analyst, they only see their own
    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
        'submitted_by' => 'analyst@test.com',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(1);
    expect($queries->first()->submitted_by)->toBe('dev@test.com');
});

// ── admin scope ────────────────────────────────────────────────────────────

it('admin sees all queries across all users', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(2);
});

it('admin can filter queries by submitted_by email', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
        'submitted_by' => 'analyst@test.com',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(1);
    expect($queries->first()->submitted_by)->toBe('analyst@test.com');
});

it('admin submitted_by filter with unknown email returns empty results', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $queries = $this->get(route('db-governor.queries', [
        'connection' => 'main',
        'submitted_by' => 'nobody@example.com',
    ]))->assertOk()->viewData('queries');

    expect($queries->count())->toBe(0);
});

// ── submitters list from config ─────────────────────────────────────────────

it('admin receives a submitters list built from config admins and employees', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $submitters = $this->get(route('db-governor.queries', [
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
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['admin@test.com', 'dev@test.com'],
    ]);

    $token = $this->loginAsGuard('admin@test.com');

    $submitters = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('submitters');

    expect(count($submitters))->toBe(count(array_unique($submitters)));
});

it('employee receives an empty submitters list (no filter UI shown)', function () {
    $token = $this->loginAsGuard('dev@test.com');

    $submitters = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('submitters');

    expect($submitters)->toBeEmpty();
});

// ── isAdmin flag passed to view ─────────────────────────────────────────────

it('isAdmin is true in view for admin user', function () {
    $token = $this->loginAsGuard('admin@test.com');

    $isAdmin = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('isAdmin');

    expect($isAdmin)->toBeTrue();
});

it('isAdmin is false in view for employee user', function () {
    $token = $this->loginAsGuard('dev@test.com');

    $isAdmin = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->viewData('isAdmin');

    expect($isAdmin)->toBeFalse();
});
