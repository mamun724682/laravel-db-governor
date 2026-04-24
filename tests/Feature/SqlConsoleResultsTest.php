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
        'db-governor.hidden_tables' => [],
        'db-governor.log_read_queries' => false,
    ]);

    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS res_tbl (id INTEGER PRIMARY KEY, label TEXT, meta TEXT)'
    );
    DB::connection('sqlite')->table('res_tbl')->insert([
        ['id' => 1, 'label' => 'Alpha', 'meta' => '{"key":"value"}'],
        ['id' => 2, 'label' => null,    'meta' => null],
        ['id' => 3, 'label' => 'Alpha', 'meta' => null], // duplicate value to catch key collision
    ]);

    $this->token = $this->loginAsGuard('admin@test.com');
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS res_tbl');
});

it('SQL execute returns rows with correct values including null', function () {
    $response = $this->post(
        route('db-governor.sql.execute', ['connection' => 'main']),
        ['sql' => 'SELECT * FROM res_tbl ORDER BY id']
    )->assertOk()->json();

    expect($response['success'])->toBeTrue();
    expect($response['rows'])->toHaveCount(3);

    // Row 2 must carry a PHP null for label (not the string "NULL")
    expect($response['rows'][1]['label'])->toBeNull();
});

it('SQL execute returns JSON column as a string value', function () {
    $response = $this->post(
        route('db-governor.sql.execute', ['connection' => 'main']),
        ['sql' => 'SELECT * FROM res_tbl WHERE id = 1']
    )->assertOk()->json();

    expect($response['rows'][0]['meta'])->toBeString();
    expect($response['rows'][0]['meta'])->toContain('key');
});

it('queries page results table uses index-based key for row cells', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // The x-for on table cells must use an index key, not the value itself
    expect($html)->toContain('x-for="(val, colIdx)');
    expect($html)->toContain(':key="colIdx"');
});

it('queries page results table renders NULL values with a null badge expression', function () {
    $html = $this->get(route('db-governor.queries', [
        'connection' => 'main',
    ]))->assertOk()->getContent();

    // Must have a conditional for null values
    expect($html)->toContain('val === null');
});
