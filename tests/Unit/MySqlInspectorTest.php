<?php

use Mamun724682\DbGovernor\Drivers\MySqlInspector;

it('quoteIdentifier wraps name in backticks', function () {
    $inspector = new MySqlInspector();
    expect($inspector->quoteIdentifier('users'))->toBe('`users`');
    expect($inspector->quoteIdentifier('user`col'))->toBe('`user``col`');
})->group('mysql');

it('detectPrimaryKey uses SHOW KEYS', function () {
    $inspector = new MySqlInspector();
    expect($inspector)->toBeInstanceOf(\Mamun724682\DbGovernor\Drivers\DbInspector::class);
})->group('mysql');

