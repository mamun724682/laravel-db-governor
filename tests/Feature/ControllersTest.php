<?php

use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

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
    ]);
    $this->token = $this->loginAsGuard('admin@test.com');
});

it('dashboard returns 200 with analytics data', function () {
    $this->get(route('db-governor.dashboard', ['connection' => 'main']))
        ->assertOk()
        ->assertViewIs('db-governor::dashboard');
});

it('queries index returns 200', function () {
    $this->get(route('db-governor.queries', ['connection' => 'main']))
        ->assertOk()
        ->assertViewIs('db-governor::queries');
});

it('queries store creates a pending query for WRITE SQL', function () {
    $this->post(route('db-governor.queries.store', ['connection' => 'main']), [
        'sql' => 'UPDATE users SET active=0 WHERE id=99',
        'name' => 'Test update',
        'description' => 'Testing',
    ])->assertRedirect();

    expect(GovernedQuery::where('status', QueryStatus::Pending->value)->count())->toBe(1);
});

it('queries action approve updates status', function () {
    $query = GovernedQuery::create([
        'connection' => 'main',
        'sql_raw' => 'UPDATE users SET x=1 WHERE id=1',
        'query_type' => 'write',
        'risk_level' => 'low',
        'status' => QueryStatus::Pending->value,
        'submitted_by' => 'dev@test.com',
    ]);

    $this->post(route('db-governor.queries.action', [
        'connection' => 'main',
        'query' => $query->id,
        'action' => 'approve',
    ]), ['note' => 'OK'])->assertRedirect();

    $query->refresh();
    expect($query->status)->toBe(QueryStatus::Approved->value);
});

it('sql execute returns JSON rows for SELECT', function () {
    $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => 'SELECT 1 as value',
    ])->assertOk()->assertJsonStructure(['success', 'type']);
});

it('table show returns 200 for a valid table', function () {
    DB::connection('sqlite')->statement('CREATE TABLE IF NOT EXISTS sample_browse (id INTEGER PRIMARY KEY, name TEXT)');

    $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'sample_browse',
    ]))->assertOk()->assertViewIs('db-governor::table');

    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS sample_browse');
});
