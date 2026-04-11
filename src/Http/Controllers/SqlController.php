<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mamun724682\DbGovernor\Enums\QueryType;
use Mamun724682\DbGovernor\Services\{QueryClassifier, QueryExecutor, RiskAnalyzer};

class SqlController
{
    public function __construct(
        private readonly QueryClassifier $classifier,
        private readonly QueryExecutor $executor,
        private readonly RiskAnalyzer $analyzer,
    ) {}

    public function execute(Request $request, string $token, string $connection): JsonResponse
    {
        $request->validate(['sql' => ['required', 'string']]);

        $sql  = $request->input('sql');
        $type = $this->classifier->classify($sql);

        if ($type === QueryType::Read) {
            $result = $this->executor->executeRead($sql, $connection);

            return response()->json([
                'success'        => $result->success,
                'type'           => 'read',
                'rows'           => $result->rows,
                'rowsAffected'   => $result->rowsAffected,
                'executionTimeMs' => $result->executionTimeMs,
                'error'          => $result->error,
            ]);
        }

        $risk = $this->analyzer->analyze($sql, $connection);

        return response()->json([
            'success'       => true,
            'type'          => 'write',
            'riskLevel'     => $risk->level->value,
            'flags'         => $risk->flags,
            'estimatedRows' => $risk->estimatedRows,
            'blocked'       => $risk->blocked,
        ]);
    }
}

