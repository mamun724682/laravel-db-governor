<?php

use Mamun724682\DbGovernor\Drivers\DbInspector;

it('DbInspector interface exists and declares all methods', function () {
    expect(interface_exists(DbInspector::class))->toBeTrue();

    $reflection = new ReflectionClass(DbInspector::class);
    $methods = array_map(fn ($m) => $m->getName(), $reflection->getMethods());

    expect($methods)->toContain('quoteIdentifier')
        ->toContain('detectPrimaryKey')
        ->toContain('listTables')
        ->toContain('listColumns')
        ->toContain('estimateAffectedRows');
});
