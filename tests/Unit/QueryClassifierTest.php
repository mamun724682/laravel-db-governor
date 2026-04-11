<?php

use Mamun724682\DbGovernor\Enums\QueryType;
use Mamun724682\DbGovernor\Services\QueryClassifier;

beforeEach(fn () => $this->classifier = new QueryClassifier());

// --- classify ---
it('classifies SELECT as READ', function () {
    expect($this->classifier->classify('SELECT * FROM users'))->toBe(QueryType::Read);
});

it('classifies SHOW as READ', function () {
    expect($this->classifier->classify('SHOW TABLES'))->toBe(QueryType::Read);
});

it('classifies WITH (CTE) as READ', function () {
    expect($this->classifier->classify('WITH cte AS (SELECT 1) SELECT * FROM cte'))->toBe(QueryType::Read);
});

it('classifies UPDATE as WRITE', function () {
    expect($this->classifier->classify('UPDATE users SET active = 0'))->toBe(QueryType::Write);
});

it('classifies INSERT as WRITE', function () {
    expect($this->classifier->classify("INSERT INTO users (name) VALUES ('Alice')"))->toBe(QueryType::Write);
});

it('classifies DELETE as WRITE', function () {
    expect($this->classifier->classify('DELETE FROM users WHERE id = 1'))->toBe(QueryType::Write);
});

it('classifies CREATE TABLE as DDL', function () {
    expect($this->classifier->classify('CREATE TABLE logs (id INT)'))->toBe(QueryType::Ddl);
});

it('classifies ALTER TABLE as DDL', function () {
    expect($this->classifier->classify('ALTER TABLE users ADD COLUMN bio TEXT'))->toBe(QueryType::Ddl);
});

it('classifies DROP TABLE as DDL', function () {
    expect($this->classifier->classify('DROP TABLE old_logs'))->toBe(QueryType::Ddl);
});

it('classifies unknown SQL as UNKNOWN', function () {
    expect($this->classifier->classify('VACUUM'))->toBe(QueryType::Unknown);
});

// --- extractVerb ---
it('strips leading block comments before extracting verb', function () {
    expect($this->classifier->extractVerb('/* comment */ SELECT * FROM users'))->toBe('SELECT');
});

it('strips leading line comments before extracting verb', function () {
    expect($this->classifier->extractVerb("-- comment\nSELECT * FROM users"))->toBe('SELECT');
});

it('handles leading whitespace', function () {
    expect($this->classifier->extractVerb('   UPDATE users SET x = 1'))->toBe('UPDATE');
});

// --- extractTables ---
it('extracts table from FROM clause', function () {
    expect($this->classifier->extractTables('SELECT * FROM users'))->toContain('users');
});

it('extracts tables from JOIN clauses', function () {
    $tables = $this->classifier->extractTables('SELECT * FROM users JOIN orders ON users.id = orders.user_id');
    expect($tables)->toContain('users')->toContain('orders');
});

it('extracts table from UPDATE', function () {
    expect($this->classifier->extractTables('UPDATE users SET active = 0'))->toContain('users');
});

it('extracts table from INSERT INTO', function () {
    expect($this->classifier->extractTables("INSERT INTO logs (msg) VALUES ('x')"))->toContain('logs');
});

it('returns unique tables only', function () {
    $tables = $this->classifier->extractTables('SELECT * FROM users JOIN users AS u2 ON users.id = u2.id');
    expect(count(array_unique($tables)))->toBe(count($tables));
});

