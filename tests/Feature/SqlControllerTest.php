<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
        'db-governor.hidden_tables' => ['secret_table', 'jobs'],
        'db-governor.blocked_patterns' => [],
        'db-governor.flagged_patterns' => [],
        'db-governor.dry_run_enabled' => false,
    ]);

    $this->token = $this->loginAsGuard('admin@test.com');
});

// ── hidden table block ─────────────────────────────────────────────────────

it('returns blocked JSON when SQL references a hidden table', function () {
    $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => 'SELECT * FROM secret_table',
    ])
        ->assertOk()
        ->assertJson(['blocked' => true]);
});

it('returns blocked JSON for hidden table referenced in a JOIN', function () {
    $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => 'SELECT u.* FROM users u JOIN jobs j ON u.id = j.user_id',
    ])
        ->assertOk()
        ->assertJson(['blocked' => true]);
});

it('blocked response contains a descriptive message', function () {
    $response = $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => 'SELECT * FROM secret_table',
    ])->assertOk()->json();

    expect($response['blocked'])->toBeTrue();
    expect($response['message'])->toBeString()->not->toBeEmpty();
});

// ── unknown table SQL error ────────────────────────────────────────────────

it('returns success false with error string for a non-existent table', function () {
    $response = $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => 'SELECT * FROM nonexistent_xyz_table_abc',
    ])->assertOk()->json();

    expect($response['success'])->toBeFalse();
    expect($response['error'])->toBeString()->not->toBeEmpty();
});

it('error message for unknown table is not empty and is a string not an array', function () {
    $response = $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => 'SELECT * FROM totally_missing_table',
    ])->assertOk()->json();

    expect($response['error'])->toBeString();
    expect($response['rows'])->toBeEmpty();
});

// ── valid visible-table SELECT ─────────────────────────────────────────────

it('returns rows successfully for a valid SELECT on a visible table', function () {
    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS sql_test_tbl (id INTEGER PRIMARY KEY, name TEXT)'
    );
    DB::connection('sqlite')->table('sql_test_tbl')->insert(['name' => 'Alice']);

    $response = $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => 'SELECT * FROM sql_test_tbl',
    ])->assertOk()->json();

    expect($response['success'])->toBeTrue();
    expect($response['type'])->toBe('read');
    expect($response['rows'])->toHaveCount(1);
    expect($response['rows'][0]['name'])->toBe('Alice');

    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS sql_test_tbl');
});

it('returns write type for a write query without executing it', function () {
    $response = $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => 'UPDATE users SET active = 0 WHERE id = 1',
    ])->assertOk()->json();

    expect($response['type'])->toBe('write');
    expect($response['sql'])->toContain('UPDATE');
});
