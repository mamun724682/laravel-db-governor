<?php

namespace Mamun724682\DbGovernor\Services;

class DryRunEngine
{
    public function __construct(private readonly ConnectionManager $connectionManager) {}

    public function estimate(string $sql, string $connectionKey): ?int
    {
        if (! config('db-governor.dry_run_enabled', true)) {
            return null;
        }

        try {
            $conn = $this->connectionManager->resolve($connectionKey);
            $inspector = $this->connectionManager->inspector($connectionKey);

            return $inspector->estimateAffectedRows($sql, $conn);
        } catch (\Throwable) {
            return null;
        }
    }
}
