<?php

use Mamun724682\DbGovernor\DTOs\RiskReport;
use Mamun724682\DbGovernor\Enums\RiskLevel;
use Mamun724682\DbGovernor\Services\{DryRunEngine, RiskAnalyzer};

function makeAnalyzer(int $maxRows = 1000, ?int $estimatedRows = null): RiskAnalyzer
{
    $dryRun = Mockery::mock(DryRunEngine::class);
    $dryRun->shouldReceive('estimate')->andReturn($estimatedRows);

    return new RiskAnalyzer(
        blockedPatterns: ['/^\s*DROP\s+(TABLE|DATABASE|SCHEMA)/i', '/^\s*TRUNCATE\s+/i'],
        flaggedPatterns: [
            '/UPDATE\s+\w[\w.]*\s+SET(?!.*\bWHERE\b)/is',
            '/DELETE\s+FROM\s+\w[\w.]*\s*(?!WHERE)/is',
        ],
        maxAffectedRows: $maxRows,
        dryRun: $dryRun,
    );
}

it('returns RiskReport instance', function () {
    $report = makeAnalyzer()->analyze('SELECT * FROM users', 'main');
    expect($report)->toBeInstanceOf(RiskReport::class);
});

it('low risk for plain SELECT', function () {
    $report = makeAnalyzer()->analyze('SELECT * FROM users', 'main');
    expect($report->level)->toBe(RiskLevel::Low);
    expect($report->blocked)->toBeFalse();
});

it('blocks DROP TABLE', function () {
    $report = makeAnalyzer()->analyze('DROP TABLE users', 'main');
    expect($report->blocked)->toBeTrue();
    expect($report->level)->toBe(RiskLevel::Critical);
});

it('blocks TRUNCATE', function () {
    $report = makeAnalyzer()->analyze('TRUNCATE users', 'main');
    expect($report->blocked)->toBeTrue();
});

it('flags UPDATE without WHERE as HIGH risk', function () {
    $report = makeAnalyzer()->analyze('UPDATE users SET active = 0', 'main');
    expect($report->level)->toBe(RiskLevel::High);
    expect($report->blocked)->toBeFalse();
    expect($report->flags)->not->toBeEmpty();
});

it('flags DELETE without WHERE as HIGH risk', function () {
    $report = makeAnalyzer()->analyze('DELETE FROM users', 'main');
    expect($report->level)->toBe(RiskLevel::High);
});

it('flags when estimated rows exceeds max', function () {
    $report = makeAnalyzer(maxRows: 100, estimatedRows: 500)->analyze(
        'UPDATE users SET x=1 WHERE active=1', 'main'
    );
    expect($report->level)->toBe(RiskLevel::High);
    expect(implode(' ', $report->flags))->toContain('500');
});

it('does not flag when estimated rows within limit', function () {
    $report = makeAnalyzer(maxRows: 1000, estimatedRows: 50)->analyze(
        'UPDATE users SET x=1 WHERE id=1', 'main'
    );
    expect($report->level)->toBe(RiskLevel::Low);
});

it('skips dry run for blocked queries', function () {
    $dryRun = Mockery::mock(DryRunEngine::class);
    $dryRun->shouldNotReceive('estimate');
    $analyzer = new RiskAnalyzer(['/DROP/i'], [], 1000, $dryRun);
    $analyzer->analyze('DROP TABLE users', 'main');
});

