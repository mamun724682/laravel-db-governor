<?php

use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins'    => ['admin@test.com'],
        'db-governor.allowed.employees' => ['dev@test.com'],
        'db-governor.connections'       => ['main' => 'sqlite'],
        'db-governor.path'              => 'db-governor',
        'db-governor.blocked_patterns'  => [],
        'db-governor.flagged_patterns'  => [],
        'db-governor.dry_run_enabled'   => false,
        'db-governor.hidden_tables'     => [],
        'db-governor.log_read_queries'  => true,
    ]);

    $guard       = app(AccessGuard::class);
    $this->token = $guard->login('dev@test.com');
    $guard->setPayload($guard->validateToken($this->token));

    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS read_log_tbl (id INTEGER PRIMARY KEY, name TEXT)'
    );
    DB::connection('sqlite')->table('read_log_tbl')->insert([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]);
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS read_log_tbl');
});

// ── log row is created ─────────────────────────────────────────────────────

it('stores a read query row in dbg_queries after successful SELECT', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk()->assertJson(['success' => true]);

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log)->not->toBeNull();
});

it('read log row has correct query_type and status', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk();

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log->query_type)->toBe('read');
    expect($log->status)->toBe('executed');
});

it('read log row records the correct executor email', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk();

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log->executed_by)->toBe('dev@test.com');
    expect($log->submitted_by)->toBe('dev@test.com');
});

it('read log row records the sql_raw', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk();

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log->sql_raw)->toBe('SELECT * FROM read_log_tbl');
});

it('read log row records executed_at timestamp', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk();

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log->executed_at)->not->toBeNull();
});

it('read log row records rows_affected count', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk();

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log->rows_affected)->toBe(2);
});

it('read log row records the connection key', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk();

    $log = GovernedQuery::where('query_type', 'read')->first();
    expect($log->connection)->toBe('main');
});

// ── log_read_queries flag ──────────────────────────────────────────────────

it('does not store read query when log_read_queries config is false', function () {
    config(['db-governor.log_read_queries' => false]);

    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk();

    expect(GovernedQuery::where('query_type', 'read')->count())->toBe(0);
});

// ── failed queries are not logged ─────────────────────────────────────────

it('does not store a read log row when SELECT fails', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM nonexistent_xyz_table',
    ])->assertOk();

    expect(GovernedQuery::where('query_type', 'read')->count())->toBe(0);
});

// ── admin can see employee read logs ──────────────────────────────────────

it('admin can see the read log row submitted by an employee', function () {
    $this->post(route('db-governor.sql.execute', ['token' => $this->token, 'connection' => 'main']), [
        'sql' => 'SELECT * FROM read_log_tbl',
    ])->assertOk();

    $guard      = app(AccessGuard::class);
    $adminToken = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($adminToken));

    $logs = GovernedQuery::where('query_type', 'read')->get();
    expect($logs->count())->toBe(1);
    expect($logs->first()->submitted_by)->toBe('dev@test.com');
});

