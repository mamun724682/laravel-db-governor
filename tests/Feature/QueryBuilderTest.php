<?php

use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections'    => ['main' => 'sqlite'],
        'db-governor.path'           => 'db-governor',
        'db-governor.hidden_tables'  => ['secret_tbl'],
    ]);

    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS qb_test (id INTEGER PRIMARY KEY, name TEXT, score INTEGER)'
    );

    $guard       = app(AccessGuard::class);
    $this->token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($this->token));
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS qb_test');
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS secret_tbl');
});

it('schema endpoint returns columns for a visible table', function () {
    $response = $this->get(route('db-governor.schema.table', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'qb_test',
    ]))->assertOk()->json();

    $names = array_column($response['columns'], 'name');
    expect($names)->toContain('id')->toContain('name')->toContain('score');
});

it('schema endpoint returns 404 for a hidden table', function () {
    DB::connection('sqlite')->statement('CREATE TABLE IF NOT EXISTS secret_tbl (id INTEGER)');

    $this->get(route('db-governor.schema.table', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'secret_tbl',
    ]))->assertNotFound();
});

it('queries page console modal contains the query builder tab', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('Query Builder');
});

it('queries page console modal contains raw SQL tab', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    expect($html)->toContain('Raw SQL');
});

