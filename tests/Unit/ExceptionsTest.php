<?php

use Mamun724682\DbGovernor\Exceptions\{
    QueryBlockedException,
    QueryNotApprovedException,
    RollbackFailedException,
    InvalidConnectionException,
};

it('QueryBlockedException holds flags', function () {
    $e = new QueryBlockedException(['flag1', 'flag2']);
    expect($e->flags)->toContain('flag1');
    expect($e->getMessage())->toContain('blocked');
});

it('QueryNotApprovedException is a RuntimeException', function () {
    $e = new QueryNotApprovedException('not approved');
    expect($e)->toBeInstanceOf(\RuntimeException::class);
});

it('RollbackFailedException wraps a previous exception', function () {
    $prev = new \Exception('db error');
    $e = new RollbackFailedException('Rollback failed: db error', previous: $prev);
    expect($e->getPrevious())->toBe($prev);
});

it('InvalidConnectionException is a RuntimeException', function () {
    $e = new InvalidConnectionException('bad key');
    expect($e)->toBeInstanceOf(\RuntimeException::class);
});

