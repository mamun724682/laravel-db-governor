<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\DTOs\SnapshotData;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\QueryExecutor;
use Mamun724682\DbGovernor\Services\RollbackService;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.snapshot_max_rows' => 500,
        'db-governor.governance_connection' => null,
    ]);

    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS del_users (id INTEGER PRIMARY KEY, name TEXT, active INTEGER DEFAULT 1)'
    );
    DB::connection('sqlite')->table('del_users')->insert([
        ['id' => 1, 'name' => 'Alice', 'active' => 1],
        ['id' => 2, 'name' => 'Bob',   'active' => 1],
        ['id' => 3, 'name' => 'Carol', 'active' => 0],
    ]);

    app(AccessGuard::class)->setPayload([
        'email' => 'admin@test.com',
        'role' => 'admin',
        'expires_at' => now()->addHour()->toISOString(),
    ]);
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS del_users');
});

// ── captureBeforeState for DELETE ─────────────────────────────────────────

it('captureBeforeState captures rows for DELETE with simple WHERE', function () {
    $snapshot = app(RollbackService::class)
        ->captureBeforeState('DELETE FROM del_users WHERE id = 1', 'main');

    expect($snapshot)->toBeInstanceOf(SnapshotData::class);
    expect($snapshot->tableName)->toBe('del_users');
    expect($snapshot->rows)->toHaveCount(1);
    expect($snapshot->rows[0]['id'])->toBe(1);
    expect($snapshot->rows[0]['name'])->toBe('Alice');
    expect($snapshot->primaryKey)->toBe('id');
});

it('captureBeforeState captures multiple rows for DELETE with IN clause', function () {
    $snapshot = app(RollbackService::class)
        ->captureBeforeState('DELETE FROM del_users WHERE id IN (1, 2)', 'main');

    expect($snapshot)->toBeInstanceOf(SnapshotData::class);
    expect($snapshot->rows)->toHaveCount(2);
});

it('captureBeforeState captures rows for DELETE with condition on non-PK column', function () {
    $snapshot = app(RollbackService::class)
        ->captureBeforeState('DELETE FROM del_users WHERE active = 0', 'main');

    expect($snapshot)->toBeInstanceOf(SnapshotData::class);
    expect($snapshot->rows)->toHaveCount(1);
    expect($snapshot->rows[0]['name'])->toBe('Carol');
});

it('captureBeforeState returns null for DELETE without WHERE clause', function () {
    $snapshot = app(RollbackService::class)
        ->captureBeforeState('DELETE FROM del_users', 'main');

    expect($snapshot)->toBeNull();
});

it('captureBeforeState returns null for DELETE that matches zero rows', function () {
    $snapshot = app(RollbackService::class)
        ->captureBeforeState('DELETE FROM del_users WHERE id = 9999', 'main');

    expect($snapshot)->toBeNull();
});

// ── rollback re-inserts deleted rows ──────────────────────────────────────

it('rollback re-inserts a single deleted row', function () {
    // Execute the DELETE first
    DB::connection('sqlite')->table('del_users')->where('id', 1)->delete();
    expect(DB::connection('sqlite')->table('del_users')->count())->toBe(2);

    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'DELETE FROM del_users WHERE id = 1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Executed->value,
        'submitted_by' => 'dev@test.com',
        'snapshot_data' => json_encode([['id' => 1, 'name' => 'Alice', 'active' => 1]]),
        'query_table' => 'del_users',
        'snapshot_primary_key' => 'id',
    ]);

    $result = app(RollbackService::class)->rollback($query);

    expect($result->success)->toBeTrue();
    expect($result->rowsRestored)->toBe(1);
    expect(DB::connection('sqlite')->table('del_users')->count())->toBe(3);

    $restored = DB::connection('sqlite')->table('del_users')->where('id', 1)->first();
    expect($restored->name)->toBe('Alice');
    expect($restored->active)->toBe(1);
});

it('rollback re-inserts multiple deleted rows', function () {
    DB::connection('sqlite')->table('del_users')->whereIn('id', [1, 2])->delete();
    expect(DB::connection('sqlite')->table('del_users')->count())->toBe(1);

    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'DELETE FROM del_users WHERE id IN (1, 2)',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Executed->value,
        'submitted_by' => 'dev@test.com',
        'snapshot_data' => json_encode([
            ['id' => 1, 'name' => 'Alice', 'active' => 1],
            ['id' => 2, 'name' => 'Bob',   'active' => 1],
        ]),
        'query_table' => 'del_users',
        'snapshot_primary_key' => 'id',
    ]);

    $result = app(RollbackService::class)->rollback($query);

    expect($result->success)->toBeTrue();
    expect($result->rowsRestored)->toBe(2);
    expect(DB::connection('sqlite')->table('del_users')->count())->toBe(3);
});

it('rollback marks query status as rolled_back after re-insert', function () {
    DB::connection('sqlite')->table('del_users')->where('id', 1)->delete();

    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'DELETE FROM del_users WHERE id = 1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Executed->value,
        'submitted_by' => 'dev@test.com',
        'snapshot_data' => json_encode([['id' => 1, 'name' => 'Alice', 'active' => 1]]),
        'query_table' => 'del_users',
        'snapshot_primary_key' => 'id',
    ]);

    app(RollbackService::class)->rollback($query);

    $query->refresh();
    expect($query->status)->toBe(QueryStatus::RolledBack->value);
    expect($query->rolled_back_by)->toBe('admin@test.com');
    expect($query->rolled_back_at)->not->toBeNull();
});

it('rollback returns failure when attempting to rollback a DELETE twice', function () {
    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'DELETE FROM del_users WHERE id = 1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::RolledBack->value,
        'submitted_by' => 'dev@test.com',
        'snapshot_data' => json_encode([['id' => 1, 'name' => 'Alice', 'active' => 1]]),
        'query_table' => 'del_users',
        'snapshot_primary_key' => 'id',
        'rolled_back_at' => now(),
    ]);

    $result = app(RollbackService::class)->rollback($query);

    expect($result->success)->toBeFalse();
    expect($result->message)->toContain('Already rolled back');
});

// ── end-to-end via QueryExecutor ──────────────────────────────────────────

it('executeWrite captures snapshot for DELETE and rollback restores rows', function () {
    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'DELETE FROM del_users WHERE id = 1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Approved->value,
        'submitted_by' => 'dev@test.com',
    ]);

    $executeResult = app(QueryExecutor::class)->executeWrite($query);
    expect($executeResult->success)->toBeTrue();

    $query->refresh();
    expect($query->snapshot_data)->not->toBeNull();
    expect($query->query_table)->toBe('del_users');
    expect(DB::connection('sqlite')->table('del_users')->count())->toBe(2);

    // Now rollback
    $rollbackResult = app(RollbackService::class)->rollback($query);
    expect($rollbackResult->success)->toBeTrue();
    expect(DB::connection('sqlite')->table('del_users')->count())->toBe(3);
});
