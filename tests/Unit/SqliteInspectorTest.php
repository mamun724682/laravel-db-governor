<?php

use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Drivers\SqliteInspector;

beforeEach(function () {
    $this->conn = DB::connection('sqlite');
    $this->conn->statement('CREATE TABLE IF NOT EXISTS test_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    $this->conn->statement("INSERT INTO test_users (name, email) VALUES ('Alice', 'a@test.com')");
    $this->conn->statement("INSERT INTO test_users (name, email) VALUES ('Bob', 'b@test.com')");
    $this->inspector = new SqliteInspector();
});

afterEach(function () {
    $this->conn->statement('DROP TABLE IF EXISTS test_users');
});

it('quoteIdentifier wraps name in double-quotes', function () {
    expect($this->inspector->quoteIdentifier('users'))->toBe('"users"');
    expect($this->inspector->quoteIdentifier('user"col'))->toBe('"user""col"');
});

it('detectPrimaryKey returns the PK column', function () {
    expect($this->inspector->detectPrimaryKey('test_users', $this->conn))->toBe('id');
});

it('detectPrimaryKey falls back to id when no PK found', function () {
    $this->conn->statement('CREATE TABLE IF NOT EXISTS no_pk (col TEXT)');
    expect($this->inspector->detectPrimaryKey('no_pk', $this->conn))->toBe('id');
    $this->conn->statement('DROP TABLE IF EXISTS no_pk');
});

it('listTables returns table names', function () {
    expect($this->inspector->listTables($this->conn))->toContain('test_users');
});

it('listColumns returns column names and types', function () {
    $cols = $this->inspector->listColumns('test_users', $this->conn);
    expect(array_column($cols, 'name'))->toContain('id')->toContain('name')->toContain('email');
});

it('estimateAffectedRows returns count via WHERE clause', function () {
    $sql = "UPDATE test_users SET name = 'X' WHERE email = 'a@test.com'";
    expect($this->inspector->estimateAffectedRows($sql, $this->conn))->toBe(1);
});

it('estimateAffectedRows returns null when no WHERE clause', function () {
    $sql = "UPDATE test_users SET name = 'X'";
    expect($this->inspector->estimateAffectedRows($sql, $this->conn))->toBeNull();
});

