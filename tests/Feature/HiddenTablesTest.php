<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ConnectionManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections' => ['main' => 'sqlite'],
        'db-governor.path' => 'db-governor',
        'db-governor.hidden_tables' => ['secret_table', 'jobs'],
    ]);

    $this->token = $this->loginAsGuard('admin@test.com');

    DB::connection('sqlite')->statement('CREATE TABLE IF NOT EXISTS visible_tbl (id INTEGER PRIMARY KEY, name TEXT)');
    DB::connection('sqlite')->statement('CREATE TABLE IF NOT EXISTS secret_table (id INTEGER PRIMARY KEY, data TEXT)');
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS visible_tbl');
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS secret_table');
});

it('returns 200 for a visible table', function () {
    $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'visible_tbl',
    ]))->assertOk();
});

it('returns 404 for a hidden table accessed via direct URL', function () {
    $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'secret_table',
    ]))->assertNotFound();
});

it('listTables excludes hidden tables', function () {
    $tables = app(ConnectionManager::class)->listTables('main');

    expect($tables)->not->toContain('secret_table')
        ->not->toContain('jobs');
});

it('listTables includes visible tables', function () {
    $tables = app(ConnectionManager::class)->listTables('main');

    expect($tables)->toContain('visible_tbl');
});

it('sidebar view does not render links for hidden tables', function () {
    $html = $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'visible_tbl',
    ]))->assertOk()->getContent();

    // The sidebar must NOT contain a link to the hidden table
    expect($html)->not->toContain('secret_table');
});

it('hidden table URL block is case-sensitive match', function () {
    // 'Secret_Table' (different case) is NOT in hidden list, so it would 404 for a different reason
    // but 'secret_table' (exact match) must be blocked
    $this->get(route('db-governor.table.show', [
        'connection' => 'main',
        'table' => 'secret_table',
    ]))->assertNotFound();
});
