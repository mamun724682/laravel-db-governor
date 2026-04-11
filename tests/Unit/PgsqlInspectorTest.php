<?php

use Mamun724682\DbGovernor\Drivers\PgsqlInspector;

it('quoteIdentifier wraps name in double-quotes', function () {
    $inspector = new PgsqlInspector();
    expect($inspector->quoteIdentifier('users'))->toBe('"users"');
    expect($inspector->quoteIdentifier('user"col'))->toBe('"user""col"');
});

it('implements DbInspector interface', function () {
    $inspector = new PgsqlInspector();
    expect($inspector)->toBeInstanceOf(\Mamun724682\DbGovernor\Drivers\DbInspector::class);
});

