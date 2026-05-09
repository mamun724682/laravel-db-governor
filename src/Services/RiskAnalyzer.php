<?php

namespace Mamun724682\DbGovernor\Services;

use Mamun724682\DbGovernor\DTOs\RiskReport;
use Mamun724682\DbGovernor\Enums\RiskLevel;

class RiskAnalyzer
{
    /**
     * @param  array<int, string>  $blockedPatterns
     * @param  array<int, string>  $flaggedPatterns
     */
    public function __construct(
        private readonly array $blockedPatterns,
        private readonly array $flaggedPatterns,
        private readonly int $maxAffectedRows,
        private readonly DryRunEngine $dryRun,
    ) {}

    public function analyze(string $sql, string $connectionKey): RiskReport
    {
        $flags = [];
        $level = RiskLevel::Low;
        $blocked = false;

        // Strip SQL comments so that comment-wrapping cannot bypass blocked patterns.
        // e.g. "/* bypass */ DROP TABLE t" must be caught by /^\s*DROP/i patterns.
        $normalizedSql = $this->stripComments($sql);

        // 1. Check blocked patterns — if matched, stop all further processing
        foreach ($this->blockedPatterns as $pattern) {
            if (preg_match($pattern, $normalizedSql)) {
                $flags[] = "Blocked by pattern: {$pattern}";
                $blocked = true;
                $level = RiskLevel::Critical;

                return new RiskReport(
                    level: $level,
                    flags: $flags,
                    blocked: $blocked,
                    estimatedRows: null,
                );
            }
        }

        // 2. Check flagged patterns — escalate to HIGH
        foreach ($this->flaggedPatterns as $pattern) {
            if (preg_match($pattern, $normalizedSql)) {
                $flags[] = "Flagged by pattern: {$pattern}";
                $level = $level->escalateTo(RiskLevel::High);
            }
        }

        // 3. Dry-run row estimation
        $estimatedRows = $this->dryRun->estimate($sql, $connectionKey);

        if ($estimatedRows !== null && $estimatedRows > $this->maxAffectedRows) {
            $flags[] = "Estimated {$estimatedRows} affected rows exceeds limit of {$this->maxAffectedRows}";
            $level = $level->escalateTo(RiskLevel::High);
        }

        return new RiskReport(
            level: $level,
            flags: $flags,
            blocked: $blocked,
            estimatedRows: $estimatedRows,
        );
    }

    private function stripComments(string $sql): string
    {
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;
        $sql = preg_replace('#^\s*--[^\n]*(\n|$)#m', '', $sql) ?? $sql;

        return ltrim($sql);
    }
}
