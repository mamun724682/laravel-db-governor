<?php

namespace Mamun724682\DbGovernor\Services;

use Mamun724682\DbGovernor\DTOs\{RollbackResult, SnapshotData};
use Mamun724682\DbGovernor\Models\GovernedQuery;

class RollbackService
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly QueryClassifier $classifier,
        private readonly AccessGuard $guard,
    ) {}

    public function captureBeforeState(string $sql, string $connectionKey): ?SnapshotData
    {
        // Implemented in Task 18
        return null;
    }

    public function rollback(GovernedQuery $query): RollbackResult
    {
        // Implemented in Task 18
        return new RollbackResult(success: false, message: 'Not yet implemented');
    }
}

