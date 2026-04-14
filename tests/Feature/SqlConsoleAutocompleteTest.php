<?php

use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Services\AccessGuard;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'db-governor.allowed.admins' => ['admin@test.com'],
        'db-governor.connections'    => ['main' => 'sqlite'],
        'db-governor.path'           => 'db-governor',
        'db-governor.hidden_tables'  => [],
    ]);

    DB::connection('sqlite')->statement(
        'CREATE TABLE IF NOT EXISTS ac_users (id INTEGER PRIMARY KEY, email TEXT, name TEXT)'
    );

    $guard       = app(AccessGuard::class);
    $this->token = $guard->login('admin@test.com');
    $guard->setPayload($guard->validateToken($this->token));
});

afterEach(function () {
    DB::connection('sqlite')->statement('DROP TABLE IF EXISTS ac_users');
});

it('queries page embeds the list of table names for autocomplete', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // Table names must be embedded as a JS array so the autocomplete can use them
    expect($html)->toContain('ac_users');
    expect($html)->toContain('autocomplete');
});

it('queries page embeds SQL keywords for autocomplete', function () {
    $html = $this->get(route('db-governor.queries', [
        'token' => $this->token, 'connection' => 'main',
    ]))->assertOk()->getContent();

    // Must contain common SQL verbs in the autocomplete list
    expect($html)->toContain('SELECT');
    expect($html)->toContain('INSERT');
    expect($html)->toContain('UPDATE');
    expect($html)->toContain('DELETE');
});

it('schema endpoint returns columns used for column-level autocomplete', function () {
    $response = $this->get(route('db-governor.schema.table', [
        'token'      => $this->token,
        'connection' => 'main',
        'table'      => 'ac_users',
    ]))->assertOk()->json();

    $names = array_column($response['columns'], 'name');
    expect($names)->toContain('id')
        ->toContain('email')
        ->toContain('name');
});

