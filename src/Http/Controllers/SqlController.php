<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Enums\QueryType;
use Mamun724682\DbGovernor\Enums\RiskLevel;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ConnectionManager;
use Mamun724682\DbGovernor\Services\QueryClassifier;
use Mamun724682\DbGovernor\Services\QueryExecutor;
use Mamun724682\DbGovernor\Services\RiskAnalyzer;

class SqlController
{
    public function __construct(
        private readonly QueryClassifier $classifier,
        private readonly QueryExecutor $executor,
        private readonly RiskAnalyzer $analyzer,
        private readonly ConnectionManager $connectionManager,
        private readonly AccessGuard $guard,
    ) {}

    public function execute(Request $request, string $token, string $connection): JsonResponse
    {
        $request->validate(['sql' => ['required', 'string']]);

        $sql = $request->input('sql');

        if ($hiddenTable = $this->connectionManager->firstHiddenTableIn($sql)) {
            return response()->json([
                'blocked' => true,
                'message' => "Access to table \"{$hiddenTable}\" is restricted.",
            ]);
        }

        $type = $this->classifier->classify($sql);

        if ($type === QueryType::Read) {
            $result = $this->executor->executeRead($sql, $connection);

            if ($result->success && config('db-governor.log_read_queries', true)) {
                GovernedQuery::create([
                    'connection'        => $connection,
                    'sql_raw'           => $sql,
                    'query_type'        => QueryType::Read->value,
                    'status'            => QueryStatus::Executed->value,
                    'risk_level'        => RiskLevel::Low->value,
                    'submitted_by'      => $this->guard->email(),
                    'executed_by'       => $this->guard->email(),
                    'executed_at'       => now(),
                    'rows_affected'     => $result->rowsAffected,
                    'execution_time_ms' => $result->executionTimeMs,
                ]);
            }

            return response()->json([
                'success' => $result->success,
                'type' => 'read',
                'rows' => $result->rows,
                'rowsAffected' => $result->rowsAffected,
                'executionTimeMs' => $result->executionTimeMs,
                'error' => $result->error,
            ]);
        }

        $risk = $this->analyzer->analyze($sql, $connection);

        return response()->json([
            'success' => true,
            'type' => 'write',
            'sql' => $sql,
            'connection' => $connection,
            'risk_level' => $risk->level->value,
            'flags' => $risk->flags,
            'estimated_rows' => $risk->estimatedRows,
            'blocked' => $risk->blocked,
        ]);
    }
}
