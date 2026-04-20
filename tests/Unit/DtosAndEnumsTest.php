<?php

use Mamun724682\DbGovernor\DTOs\PendingQuery;
use Mamun724682\DbGovernor\DTOs\QueryResult;
use Mamun724682\DbGovernor\DTOs\RiskReport;
use Mamun724682\DbGovernor\DTOs\RollbackResult;
use Mamun724682\DbGovernor\DTOs\SnapshotData;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Enums\QueryType;
use Mamun724682\DbGovernor\Enums\RiskLevel;

// --- DTOs ---
it('creates PendingQuery DTO', function () {
    $dto = new PendingQuery(sql: 'SELECT 1', connection: 'main');
    expect($dto->sql)->toBe('SELECT 1');
    expect($dto->connection)->toBe('main');
    expect($dto->name)->toBeNull();
});

it('creates QueryResult DTO for success', function () {
    $result = new QueryResult(success: true, rows: [['id' => 1]], rowsAffected: 1, executionTimeMs: 5);
    expect($result->success)->toBeTrue();
    expect($result->rowsAffected)->toBe(1);
    expect($result->error)->toBeNull();
});

it('creates RiskReport DTO', function () {
    $report = new RiskReport(level: RiskLevel::High, flags: ['flag1'], blocked: false, estimatedRows: 100);
    expect($report->level)->toBe(RiskLevel::High);
    expect($report->flags)->toContain('flag1');
    expect($report->blocked)->toBeFalse();
});

it('creates SnapshotData DTO', function () {
    $snapshot = new SnapshotData(strategy: 'row_snapshot', tableName: 'users', rows: [], primaryKey: 'id');
    expect($snapshot->strategy)->toBe('row_snapshot');
    expect($snapshot->tableName)->toBe('users');
});

it('creates RollbackResult DTO', function () {
    $result = new RollbackResult(success: true, rowsRestored: 5, message: null);
    expect($result->success)->toBeTrue();
    expect($result->rowsRestored)->toBe(5);
});

// --- Enums ---
it('QueryType READ isRead returns true', function () {
    expect(QueryType::Read->isRead())->toBeTrue();
    expect(QueryType::Write->isRead())->toBeFalse();
    expect(QueryType::Ddl->isRead())->toBeFalse();
    expect(QueryType::Unknown->isRead())->toBeFalse();
});

it('QueryStatus has all required cases', function () {
    expect(QueryStatus::Pending->value)->toBe('pending');
    expect(QueryStatus::Approved->value)->toBe('approved');
    expect(QueryStatus::Rejected->value)->toBe('rejected');
    expect(QueryStatus::Executed->value)->toBe('executed');
    expect(QueryStatus::RolledBack->value)->toBe('rolled_back');
    expect(QueryStatus::Blocked->value)->toBe('blocked');
});

it('RiskLevel escalateTo returns highest level', function () {
    expect(RiskLevel::Low->escalateTo(RiskLevel::High))->toBe(RiskLevel::High);
    expect(RiskLevel::High->escalateTo(RiskLevel::Low))->toBe(RiskLevel::High);
    expect(RiskLevel::Critical->escalateTo(RiskLevel::High))->toBe(RiskLevel::Critical);
});
