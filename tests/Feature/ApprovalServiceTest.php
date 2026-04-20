<?php

use Mamun724682\DbGovernor\DTOs\PendingQuery;
use Mamun724682\DbGovernor\Enums\QueryStatus;
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
