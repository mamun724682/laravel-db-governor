<?php

use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\DTOs\SnapshotData;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\{AccessGuard, RollbackService};

beforeEach(function () {
    config([
        'db-governor.connections'           => ['main' => 'sqlite'],
        'db-governor.snapshot_max_rows'     => 500,
        'db-governor.governance_connection' => null,
    ]);
    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS rb_users (id INTEGER PRIMARY KEY, name TEXT, active INTEGER DEFAULT 1)'
    );
    DB::connection('sqlite')->table('rb_users')->insert([
        ['id' => 1, 'name' => 'Alice', 'active' => 1],
        ['id' => 2, 'name' => 'Bob',   'active' => 1],
    ]);
    $guard = app(AccessGuard::class);
    $guard->setPayload(['email' => 'admin@test.com', 'role' => 'admin', 'expires_at' => now()->addHour()->toISOString()]);
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS rb_users');
});

it('captureBeforeState returns null for INSERT', function () {
    $snapshot = app(RollbackService::class)
        ->captureBeforeState("INSERT INTO rb_users (name) VALUES ('Carol')", 'main');
    expect($snapshot)->toBeNull();
});

it('captureBeforeState returns null when no WHERE clause', function () {
    $snapshot = app(RollbackService::class)
        ->captureBeforeState('UPDATE rb_users SET active = 0', 'main');
    expect($snapshot)->toBeNull();
});

it('captureBeforeState returns SnapshotData for UPDATE with WHERE', function () {
    $snapshot = app(RollbackService::class)
        ->captureBeforeState('UPDATE rb_users SET active = 0 WHERE id = 1', 'main');

    expect($snapshot)->toBeInstanceOf(SnapshotData::class);
    expect($snapshot->tableName)->toBe('rb_users');
    expect($snapshot->rows)->toHaveCount(1);
    expect($snapshot->primaryKey)->toBe('id');
});

it('rollback returns failure when no snapshot', function () {
    $query = GovernedQuery::create([
        'connection'    => 'main',
        'sql_raw'       => 'UPDATE rb_users SET active=0 WHERE id=1',
        'query_type'    => 'write',
        'risk_level'    => 'low',
        'status'        => QueryStatus::Executed->value,
        'submitted_by'  => 'dev@test.com',
        'snapshot_data' => null,
    ]);

    $result = app(RollbackService::class)->rollback($query);
    expect($result->success)->toBeFalse();
    expect($result->message)->toContain('No snapshot');
});

it('rollback returns failure when already rolled back', function () {
    $query = GovernedQuery::create([
        'connection'           => 'main',
        'sql_raw'              => 'UPDATE rb_users SET active=0 WHERE id=1',
        'query_type'           => 'write',
        'risk_level'           => 'low',
        'status'               => QueryStatus::RolledBack->value,
        'submitted_by'         => 'dev@test.com',
        'snapshot_data'        => json_encode([['id' => 1, 'name' => 'Alice', 'active' => 1]]),
        'query_table'       => 'rb_users',
        'snapshot_primary_key' => 'id',
        'rolled_back_at'       => now(),
    ]);

    $result = app(RollbackService::class)->rollback($query);
    expect($result->success)->toBeFalse();
    expect($result->message)->toContain('Already rolled back');
});

it('rollback restores rows and updates status to rolled_back', function () {
    DB::connection('sqlite')->table('rb_users')->where('id', 1)->update(['active' => 0]);

    $query = GovernedQuery::create([
        'connection'           => 'main',
        'sql_raw'              => 'UPDATE rb_users SET active=0 WHERE id=1',
        'query_type'           => 'write',
        'risk_level'           => 'low',
        'status'               => QueryStatus::Executed->value,
        'submitted_by'         => 'dev@test.com',
        'snapshot_data'        => json_encode([['id' => 1, 'name' => 'Alice', 'active' => 1]]),
        'query_table'       => 'rb_users',
        'snapshot_primary_key' => 'id',
    ]);

    $result = app(RollbackService::class)->rollback($query);

    expect($result->success)->toBeTrue();
    expect($result->rowsRestored)->toBe(1);

    $restored = DB::connection('sqlite')->table('rb_users')->where('id', 1)->first();
    expect($restored->active)->toBe(1);

    $query->refresh();
    expect($query->status)->toBe(QueryStatus::RolledBack->value);
});

