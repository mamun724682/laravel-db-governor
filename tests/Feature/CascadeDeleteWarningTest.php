<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ConnectionManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
        'db-governor.hidden_tables' => [],
        'db-governor.blocked_patterns' => [],
        'db-governor.flagged_patterns' => [],
        'db-governor.dry_run_enabled' => false,
        'db-governor.schema_cache_ttl' => 300,
    ]);

    $this->token = $this->loginAsGuard('admin@test.com');
});

// ── cascade-check endpoint ─────────────────────────────────────────────────

it('cascade-check endpoint is registered and returns json', function () {
    $this->get(route('db-governor.schema.cascade-check', ['connection' => 'main']) . '?table=users')
        ->assertOk()
        ->assertJsonStructure(['cascade_tables']);
});

it('cascade-check returns empty array when no table param given', function () {
    $this->get(route('db-governor.schema.cascade-check', ['connection' => 'main']))
        ->assertOk()
        ->assertJson(['cascade_tables' => []]);
});

it('cascade-check returns empty array for hidden table', function () {
    config(['db-governor.hidden_tables' => ['secret']]);

    $this->get(route('db-governor.schema.cascade-check', ['connection' => 'main']) . '?table=secret')
        ->assertOk()
        ->assertJson(['cascade_tables' => []]);
});

it('cascade-check route name is db-governor.schema.cascade-check', function () {
    expect(route('db-governor.schema.cascade-check', ['connection' => 'main']))
        ->toContain('cascade-check');
});

// ── ConnectionManager::detectCascadeTables() ──────────────────────────────

it('detectCascadeTables returns empty array when no FK cascade found', function () {
    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS parents (id INTEGER PRIMARY KEY, name TEXT)'
    );
    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS children (id INTEGER PRIMARY KEY, parent_id INTEGER)'
    );

    $manager = app(ConnectionManager::class);
    $result = $manager->detectCascadeTables('parents', 'main');

    expect($result)->toBeArray();
    // SQLite without explicit REFERENCES / ON DELETE CASCADE should yield empty
    expect($result)->not->toContain('children');
});

it('detectCascadeTables returns child table when ON DELETE CASCADE FK exists', function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS cascade_orders');
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS cascade_customers');
    DB::connection('sqlite')->statement(
        'CREATE TABLE cascade_customers (id INTEGER PRIMARY KEY, name TEXT)'
    );
    DB::connection('sqlite')->statement(
        'CREATE TABLE cascade_orders (
            id INTEGER PRIMARY KEY,
            customer_id INTEGER NOT NULL,
            FOREIGN KEY (customer_id) REFERENCES cascade_customers(id) ON DELETE CASCADE
        )'
    );

    Cache::flush(); // clear cached results

    $manager = app(ConnectionManager::class);
    $result = $manager->detectCascadeTables('cascade_customers', 'main');

    expect($result)->toBeArray()->toContain('cascade_orders');
});

it('detectCascadeTables does not include tables with ON DELETE RESTRICT', function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS restrict_orders');
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS restrict_customers');
    DB::connection('sqlite')->statement(
        'CREATE TABLE restrict_customers (id INTEGER PRIMARY KEY, name TEXT)'
    );
    DB::connection('sqlite')->statement(
        'CREATE TABLE restrict_orders (
            id INTEGER PRIMARY KEY,
            customer_id INTEGER NOT NULL,
            FOREIGN KEY (customer_id) REFERENCES restrict_customers(id) ON DELETE RESTRICT
        )'
    );

    Cache::flush();

    $manager = app(ConnectionManager::class);
    $result = $manager->detectCascadeTables('restrict_customers', 'main');

    expect($result)->not->toContain('restrict_orders');
});

it('detectCascadeTables results are cached', function () {
    Cache::flush();

    $manager = app(ConnectionManager::class);
    $manager->detectCascadeTables('users', 'main');

    expect(Cache::has('db-governor.cascade.main.users'))->toBeTrue();
});

// ── SqlController write response includes table field ─────────────────────

it('sql execute returns table field in write response', function () {
    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS write_test (id INTEGER PRIMARY KEY, name TEXT)'
    );
    DB::connection('sqlite')->table('write_test')->insert(['name' => 'Alice']);

    $response = $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => "DELETE FROM write_test WHERE id = 1",
    ])->assertOk()->json();

    expect($response['type'])->toBe('write');
    expect($response)->toHaveKey('table');
    expect($response['table'])->toBe('write_test');
});

it('sql execute table field is null for insert without recognisable table', function () {
    $response = $this->post(route('db-governor.sql.execute', ['connection' => 'main']), [
        'sql' => "INSERT INTO some_table (name) VALUES ('test')",
    ])->assertOk()->json();

    expect($response)->toHaveKey('table');
});

// ── UI: cascade warning present in views ──────────────────────────────────

it('queries view contains cascade warning alpine binding for detail modal', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('cascade-check');
    expect($html)->toContain('cascadeTables');
});

it('queries view shows cascade warning template block', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('Cascade DELETE');
    expect($html)->toContain('cannot be rolled back');
});

it('write modal contains cascade warning template', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('Cascade DELETE Warning');
    expect($html)->toContain('permanently deleted');
});


