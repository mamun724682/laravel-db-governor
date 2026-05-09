<?php

use Mamun724682\DbGovernor\DTOs\PendingQuery;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Exceptions\InvalidTransitionException;
use Mamun724682\DbGovernor\Exceptions\QueryBlockedException;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ApprovalService;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.blocked_patterns' => ['/^\s*DROP\s+(TABLE|DATABASE)/i'],
        'db-governor.flagged_patterns' => [],
        'db-governor.max_affected_rows' => 1000,
        'db-governor.dry_run_enabled' => false,
    ]);

    $guard = app(AccessGuard::class);
    $token = $guard->login('dev@test.com');
    $guard->setPayload($guard->validateToken($token));
});

it('submit creates a pending GovernedQuery for WRITE sql', function () {
    $service = app(ApprovalService::class);
    $dto = new PendingQuery(sql: 'UPDATE users SET active=0 WHERE id=1', connection: 'main', name: 'Fix', description: 'Test');

    $query = $service->submit($dto);

    expect($query)->toBeInstanceOf(GovernedQuery::class);
    expect($query->status)->toBe(QueryStatus::Pending->value);
    expect($query->submitted_by)->toBe('dev@test.com');
});

it('submit throws QueryBlockedException and stores blocked row for blocked SQL', function () {
    $service = app(ApprovalService::class);
    $dto = new PendingQuery(sql: 'DROP TABLE users', connection: 'main', name: 'Drop', description: 'bad');

    expect(fn () => $service->submit($dto))->toThrow(QueryBlockedException::class);

    $blocked = GovernedQuery::where('status', QueryStatus::Blocked->value)->first();
    expect($blocked)->not->toBeNull();
});

it('approve sets status to approved and requires admin', function () {
    $guard = app(AccessGuard::class);
    $token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($token));

    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE users SET x=1 WHERE id=1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    app(ApprovalService::class)->approve($query->id, 'Looks good');

    $query->refresh();
    expect($query->status)->toBe(QueryStatus::Approved->value);
    expect($query->reviewed_by)->toBe('admin@test.com');
    expect($query->review_note)->toBe('Looks good');
});

it('approve throws 403 for non-admin', function () {
    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE users SET x=1 WHERE id=1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    expect(fn () => app(ApprovalService::class)->approve($query->id))
        ->toThrow(HttpException::class);
});

it('submit populates submitted_ip from the current request', function () {
    $service = app(ApprovalService::class);
    $dto = new PendingQuery(sql: 'UPDATE users SET active=0 WHERE id=1', connection: 'main', name: 'IP test');

    $query = $service->submit($dto);

    expect($query->submitted_ip)->not->toBeNull();
    expect($query->submitted_ip)->toBe(request()->ip());
});

// ── Status transition guards ──────────────────────────────────────────────

it('approve throws InvalidTransitionException when query is not pending', function () {
    $guard = app(AccessGuard::class);
    $guard->setPayload(['email' => 'admin@test.com', 'role' => 'admin', 'expires_at' => now()->addHour()->toISOString()]);

    $query = GovernedQuery::create([
        'connection' => 'main', 'sql_raw' => 'UPDATE users SET x=1 WHERE id=1',
        'query_type' => 'write', 'risk_level' => 'low',
        'status' => QueryStatus::Executed->value, 'submitted_by' => 'dev@test.com',
    ]);

    expect(fn () => app(ApprovalService::class)->approve($query->id))
        ->toThrow(InvalidTransitionException::class);
});

it('reject throws InvalidTransitionException when query is not pending', function () {
    $guard = app(AccessGuard::class);
    $guard->setPayload(['email' => 'admin@test.com', 'role' => 'admin', 'expires_at' => now()->addHour()->toISOString()]);

    $query = GovernedQuery::create([
        'connection' => 'main', 'sql_raw' => 'UPDATE users SET x=1 WHERE id=1',
        'query_type' => 'write', 'risk_level' => 'low',
        'status' => QueryStatus::Approved->value, 'submitted_by' => 'dev@test.com',
    ]);

    expect(fn () => app(ApprovalService::class)->reject($query->id, 'nope'))
        ->toThrow(InvalidTransitionException::class);
});

it('rollback throws InvalidTransitionException when query is not executed', function () {
    $guard = app(AccessGuard::class);
    $guard->setPayload(['email' => 'admin@test.com', 'role' => 'admin', 'expires_at' => now()->addHour()->toISOString()]);

    $query = GovernedQuery::create([
        'connection' => 'main', 'sql_raw' => 'UPDATE users SET x=1 WHERE id=1',
        'query_type' => 'write', 'risk_level' => 'low',
        'status' => QueryStatus::Pending->value, 'submitted_by' => 'dev@test.com',
    ]);

    expect(fn () => app(ApprovalService::class)->rollback($query->id))
        ->toThrow(InvalidTransitionException::class);
});

it('reject sets status to rejected with reason', function () {
    $guard = app(AccessGuard::class);
    $guard->setPayload(['email' => 'admin@test.com', 'role' => 'admin', 'expires_at' => now()->addHour()->toISOString()]);

    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE users SET x=1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    app(ApprovalService::class)->reject($query->id, 'Too risky');

    $query->refresh();
    expect($query->status)->toBe(QueryStatus::Rejected->value);
    expect($query->review_note)->toBe('Too risky');
});

// ── preCheckWhereRows injection fix ──────────────────────────────────────────

it('submit accepts UPDATE with a subquery in WHERE without executing the subquery separately', function () {
    // The old code extracted the WHERE clause and injected it into a separate COUNT query.
    // A subquery in WHERE like "(SELECT id FROM ...)" would run against the DB as a side-channel.
    // After the fix, the pre-check goes through DryRunEngine (EXPLAIN on production drivers)
    // or returns null on SQLite — the raw WHERE string is never re-injected into a new query.
    config(['db-governor.dry_run_enabled' => true]);

    $service = app(ApprovalService::class);
    // Subquery in WHERE — the old injection would execute: SELECT COUNT(*) ... WHERE (SELECT 1)
    $dto = new PendingQuery(
        sql: 'UPDATE users SET active=0 WHERE id IN (SELECT id FROM users WHERE active=1)',
        connection: 'main',
        name: 'Subquery WHERE test',
    );

    // Should submit without error (not throw due to the subquery being injected into another query)
    $query = $service->submit($dto);
    expect($query->status)->toBe(QueryStatus::Pending->value);
});

it('submit skips pre-check and accepts UPDATE with WHERE when dry_run is disabled', function () {
    config(['db-governor.dry_run_enabled' => false]);

    $service = app(ApprovalService::class);
    $dto = new PendingQuery(
        sql: 'UPDATE users SET active=0 WHERE id=9999',
        connection: 'main',
        name: 'Dry run disabled test',
    );

    $query = $service->submit($dto);
    expect($query->status)->toBe(QueryStatus::Pending->value);
});
