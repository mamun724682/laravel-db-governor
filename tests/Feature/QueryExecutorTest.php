<?php

use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\DTOs\QueryResult;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Exceptions\QueryNotApprovedException;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\QueryExecutor;

beforeEach(function () {
    config([
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.rollback_strategy' => 'row_snapshot',
        'db-governor.snapshot_max_rows' => 500,
        'db-governor.governance_connection' => null,
    ]);
    $guard = app(AccessGuard::class);
    $guard->setPayload(['email' => 'admin@test.com', 'role' => 'admin', 'expires_at' => now()->addHour()->toISOString()]);
});

it('executeRead returns successful QueryResult with rows', function () {
    $result = app(QueryExecutor::class)->executeRead('SELECT 1 as n', 'main');

    expect($result)->toBeInstanceOf(QueryResult::class);
    expect($result->success)->toBeTrue();
    expect($result->rows)->not->toBeEmpty();
});

it('executeRead returns failed QueryResult on invalid SQL', function () {
    $result = app(QueryExecutor::class)->executeRead('SELECT * FROM nonexistent_table_xyz', 'main');

    expect($result->success)->toBeFalse();
    expect($result->error)->not->toBeNull();
});

it('executeWrite throws QueryNotApprovedException when not approved', function () {
    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'CREATE TABLE tmp_test (id INTEGER)',
        'query_type' => 'ddl',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    expect(fn () => app(QueryExecutor::class)->executeWrite($query))
        ->toThrow(QueryNotApprovedException::class);
});

it('executeWrite executes approved query and updates status to executed', function () {
    DB::connection('sqlite')->statement('CREATE TABLE IF NOT EXISTS tmp_exec_test (id INTEGER, val TEXT)');

    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => "INSERT INTO tmp_exec_test (id, val) VALUES (1, 'test')",
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Approved->value,
        'submitted_by' => 'dev@test.com',
    ]);

    $result = app(QueryExecutor::class)->executeWrite($query);

    expect($result->success)->toBeTrue();
    $query->refresh();
    expect($query->status)->toBe(QueryStatus::Executed->value);
    expect($query->executed_by)->toBe('admin@test.com');
    expect($query->rows_affected)->toBe(1);

    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS tmp_exec_test');
});
