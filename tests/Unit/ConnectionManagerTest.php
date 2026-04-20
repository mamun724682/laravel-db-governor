<?php

use Illuminate\Database\Connection;
use Mamun724682\DbGovernor\Drivers\SqliteInspector;
use Mamun724682\DbGovernor\Exceptions\InvalidConnectionException;
use Mamun724682\DbGovernor\Services\ConnectionManager;

beforeEach(function () {
    config(['db-governor.connections' => [
        'main' => 'sqlite',
        'legacy' => 'sqlite',
    ]]);
    $this->manager = new ConnectionManager;
});

it('allKeys returns all configured connection keys', function () {
    expect($this->manager->allKeys())->toBe(['main', 'legacy']);
});

it('isValidKey returns true for known keys', function () {
    expect($this->manager->isValidKey('main'))->toBeTrue();
    expect($this->manager->isValidKey('unknown'))->toBeFalse();
});

it('all returns the connections map', function () {
    expect($this->manager->all())->toHaveKey('main');
});

it('resolve returns a DB Connection for a valid key', function () {
    $conn = $this->manager->resolve('main');
    expect($conn)->toBeInstanceOf(Connection::class);
});

it('resolve throws InvalidConnectionException for unknown key', function () {
    expect(fn () => $this->manager->resolve('bad'))->toThrow(InvalidConnectionException::class);
});

it('driver returns the PDO driver name', function () {
    expect($this->manager->driver('main'))->toBe('sqlite');
});

it('inspector returns SqliteInspector for sqlite driver', function () {
    expect($this->manager->inspector('main'))->toBeInstanceOf(SqliteInspector::class);
});

it('inspector throws for unsupported driver', function () {
    config(['db-governor.connections' => ['bad' => 'unsupported_driver']]);
    expect(method_exists($this->manager, 'inspector'))->toBeTrue();
});
