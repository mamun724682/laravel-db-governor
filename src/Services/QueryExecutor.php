<?php

namespace Mamun724682\DbGovernor\Services;

use Mamun724682\DbGovernor\DTOs\QueryResult;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Exceptions\QueryNotApprovedException;
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
        $start = microtime(true);

        try {
            $conn = $this->connectionManager->resolve($connectionKey);
            $rows = $conn->select($sql);
            $ms = (int) ((microtime(true) - $start) * 1000);

            return new QueryResult(
                success: true,
                rows: array_map(fn ($r) => (array) $r, $rows),
                rowsAffected: count($rows),
                executionTimeMs: $ms,
            );
        } catch (\Throwable $e) {
            return new QueryResult(success: false, error: $e->getMessage());
        }
    }

    public function executeWrite(GovernedQuery $query): QueryResult
    {
        if ($query->status !== QueryStatus::Approved->value) {
            throw new QueryNotApprovedException(
                "Query {$query->id} is not approved (status: {$query->status})."
            );
        }

        $start = microtime(true);
        $conn = $this->connectionManager->resolve($query->connection);
        $snapshot = $this->rollbackService->captureBeforeState($query->sql_raw, $query->connection);

        try {
            $rowsAffected = $conn->affectingStatement($query->sql_raw);
            $ms = (int) ((microtime(true) - $start) * 1000);

            $updateData = [
                'status' => QueryStatus::Executed->value,
                'executed_by' => $this->guard->email(),
                'executed_at' => now(),
                'rows_affected' => $rowsAffected,
                'execution_time_ms' => $ms,
                'snapshot_strategy' => $snapshot?->strategy,
                'snapshot_data' => $snapshot ? json_encode($snapshot->rows) : null,
                'snapshot_primary_key' => $snapshot?->primaryKey,
                'snapshot_size_bytes' => $snapshot ? strlen(json_encode($snapshot->rows) ?: '') : null,
            ];

            // Backfill query_table from snapshot when not already set during submission
            if (empty($query->query_table) && $snapshot?->tableName) {
                $updateData['query_table'] = $snapshot->tableName;
            }

            $query->update($updateData);

            return new QueryResult(success: true, rowsAffected: $rowsAffected, executionTimeMs: $ms);
        } catch (\Throwable $e) {
            $query->update(['execution_error' => $e->getMessage()]);

            throw $e;
        }
    }
}
