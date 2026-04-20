<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
        'db-governor.blocked_patterns' => [],
        'db-governor.flagged_patterns' => [],
        'db-governor.max_affected_rows' => 1000,
        'db-governor.dry_run_enabled' => false,
        'db-governor.hidden_tables' => [],
    ]);

    $guard = app(AccessGuard::class);
    $this->token = $guard->login('dev@test.com');
    $guard->setPayload($guard->validateToken($this->token));
});

// ── risk_note ─────────────────────────────────────────────────────────────

it('stores risk_note in the database when submitted', function () {
    $this->post(route('db-governor.queries.store', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'UPDATE users SET active=0 WHERE id=1',
        'name' => 'Deactivate user',
        'description' => 'Fix for issue #42',
        'risk_note' => 'Verified with product team on 2026-04-12',
    ])->assertRedirect();

    $query = GovernedQuery::where('status', QueryStatus::Pending->value)->first();
    expect($query)->not->toBeNull();
    expect($query->risk_note)->toBe('Verified with product team on 2026-04-12');
});

it('risk_note is null when not provided', function () {
    $this->post(route('db-governor.queries.store', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'UPDATE users SET active=0 WHERE id=1',
        'name' => 'Test',
        'description' => 'No risk note',
    ])->assertRedirect();

    $query = GovernedQuery::where('status', QueryStatus::Pending->value)->first();
    expect($query->risk_note)->toBeNull();
});

// ── risk_level ────────────────────────────────────────────────────────────

it('stores risk_level as a plain string (not an array or object)', function () {
    $this->post(route('db-governor.queries.store', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'UPDATE users SET active=0 WHERE id=1',
        'name' => 'Test',
        'description' => 'Test query',
    ])->assertRedirect();

    $query = GovernedQuery::where('status', QueryStatus::Pending->value)->first();
    expect($query->risk_level)->toBeString();
    expect($query->risk_level)->toBeIn(['low', 'medium', 'high', 'critical']);
});

it('risk_level is low for a simple safe query', function () {
    $this->post(route('db-governor.queries.store', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'UPDATE users SET active=0 WHERE id=1',
        'name' => 'Safe update',
        'description' => 'Single row update',
    ])->assertRedirect();

    $query = GovernedQuery::where('status', QueryStatus::Pending->value)->first();
    expect($query->risk_level)->toBe('low');
});

it('risk_flags is stored as array (or null), never as a plain string', function () {
    $this->post(route('db-governor.queries.store', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'UPDATE users SET active=0 WHERE id=1',
        'name' => 'Test',
        'description' => 'Test',
    ])->assertRedirect();

    $query = GovernedQuery::where('status', QueryStatus::Pending->value)->first();
    // risk_flags cast as array: must be array or null, never a raw string
    expect($query->risk_flags)->toBeArray();
});

// ── description ───────────────────────────────────────────────────────────

it('stores description in the database', function () {
    $this->post(route('db-governor.queries.store', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'UPDATE users SET active=0 WHERE id=1',
        'name' => 'Test',
        'description' => 'My detailed description text',
    ])->assertRedirect();

    $query = GovernedQuery::where('status', QueryStatus::Pending->value)->first();
    expect($query->description)->toBe('My detailed description text');
});

it('stores submitted_by as the logged-in user email', function () {
    $this->post(route('db-governor.queries.store', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'UPDATE users SET active=0 WHERE id=1',
        'name' => 'Test',
        'description' => 'Test',
    ])->assertRedirect();

    $query = GovernedQuery::where('status', QueryStatus::Pending->value)->first();
    expect($query->submitted_by)->toBe('dev@test.com');
});

// ── write modal URL ───────────────────────────────────────────────────────

it('dashboard HTML does not contain the literal string /undefined/', function () {
    $guard = app(AccessGuard::class);
    $adminToken = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($adminToken));

    $html = $this->get(route('db-governor.dashboard', [
        'token' => $adminToken,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->not->toContain('/undefined/');
});

it('dashboard HTML contains the token value inside the write modal area', function () {
    $guard = app(AccessGuard::class);
    $adminToken = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($adminToken));

    $html = $this->get(route('db-governor.dashboard', [
        'token' => $adminToken,
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // The server-rendered tokenBaseUrl must include the real 32-char token
    expect($html)->toContain($adminToken);
});
