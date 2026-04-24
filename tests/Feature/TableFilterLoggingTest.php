<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
        'db-governor.hidden_tables' => [],
        'db-governor.log_read_queries' => true,
    ]);

    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS filter_log_tbl (id INTEGER PRIMARY KEY, status TEXT)'
    );
    for ($i = 1; $i <= 5; $i++) {
        DB::connection('sqlite')->table('filter_log_tbl')->insert([
            'id' => $i, 'status' => $i % 2 === 0 ? 'active' : 'inactive',
        ]);
    }

    $this->token = $this->loginAsGuard('dev@test.com');
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS filter_log_tbl');
});

it('does not log a read entry when browsing a table without filters', function () {
    $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'filter_log_tbl',
    ]))->assertOk();

    expect(GovernedQuery::where('query_type', 'read')->count())->toBe(0);
});

it('logs a read entry when browsing a table with active filters', function () {
    $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'filter_log_tbl',
        'f' => [[['col' => 'status', 'op' => '=', 'val' => 'active']]],
    ]))->assertOk();

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('executed');
    expect($log->submitted_by)->toBe('dev@test.com');
    expect($log->executed_by)->toBe('dev@test.com');
    expect($log->sql_raw)->toContain('filter_log_tbl');
    expect($log->executed_at)->not->toBeNull();
});

it('logged filter entry includes the WHERE clause in sql_raw', function () {
    $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'filter_log_tbl',
        'f' => [[['col' => 'status', 'op' => '=', 'val' => 'active']]],
    ]))->assertOk();

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log->sql_raw)->toContain('WHERE');
    expect($log->sql_raw)->toContain('status');
});

it('does not log when log_read_queries config is false', function () {
    config(['db-governor.log_read_queries' => false]);

    $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'filter_log_tbl',
        'f' => [[['col' => 'status', 'op' => '=', 'val' => 'active']]],
    ]))->assertOk();

    expect(GovernedQuery::where('query_type', 'read')->count())->toBe(0);
});
