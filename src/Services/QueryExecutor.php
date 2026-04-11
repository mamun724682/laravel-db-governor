<?php

namespace Mamun724682\DbGovernor\Services;

use Mamun724682\DbGovernor\DTOs\QueryResult;
use Mamun724682\DbGovernor\Models\GovernedQuery;

class QueryExecutor
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly RollbackService $rollbackService,
        private readonly AccessGuard $guard,
    ) {}

    public function executeRead(string $sql, string $connectionKey): QueryResult
    {
        // Implemented in Task 17
        return new QueryResult(success: false, error: 'Not yet implemented');
    }

    public function executeWrite(GovernedQuery $query): QueryResult
    {
        // Implemented in Task 17
        return new QueryResult(success: false, error: 'Not yet implemented');
    }
}

