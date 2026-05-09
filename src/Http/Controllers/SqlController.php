<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mamun724682\DbGovernor\Enums\QueryType;
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

    public function execute(Request $request, string $connection): JsonResponse
    {
        $request->validate(['sql' => ['required', 'string', 'not_regex:/^\s*$/']]);

        $sql = trim($request->input('sql'));

        // Reject strings that do not start with a recognised SQL verb
        $knownVerbs = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP',
            'ALTER', 'TRUNCATE', 'WITH', 'REPLACE', 'EXPLAIN'];
        $firstWord = strtoupper(strtok($sql, " \t\n\r"));

        if (! in_array($firstWord, $knownVerbs, true)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid SQL: statement must begin with a recognised SQL verb (SELECT, INSERT, UPDATE, …).',
            ]);
        }

        if ($hiddenTable = $this->connectionManager->firstHiddenTableIn($sql)) {
            return response()->json([
                'blocked' => true,
                'message' => "Access to table \"{$hiddenTable}\" is restricted.",
            ]);
        }

        $type = $this->classifier->classify($sql);

        if ($type === QueryType::Read) {
            $result = $this->executor->executeRead($sql, $connection);

            if ($result->success) {
                $this->executor->logRead(
                    $connection,
                    $this->classifier->extractTables($sql)[0] ?? null,
                    $this->nameFromSql($sql),
                    $sql,
                    $result->rowsAffected ?? 0,
                    $result->executionTimeMs,
                );
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
            'table' => $this->classifier->extractTables($sql)[0] ?? null,
            'risk_level' => $risk->level->value,
            'flags' => $risk->flags,
            'estimated_rows' => $risk->estimatedRows,
            'blocked' => $risk->blocked,
        ]);
    }

    /**
     * Generate a short human-readable name from a raw SQL string.
     * E.g. "SELECT * FROM users WHERE id = 1" → "Read: users"
     */
    private function nameFromSql(string $sql): string
    {
        $sql = trim($sql);
        $verb = strtoupper(strtok($sql, " \t\n\r") ?: 'SELECT');
        $pattern = '/\bFROM\s+[`"\[]?(\w+)[`"\]]?/i';

        if (preg_match($pattern, $sql, $m)) {
            $table = $m[1];
            $hasWhere = (bool) preg_match('/\bWHERE\b/i', $sql);

            return "Read: {$table}".($hasWhere ? ' (filtered)' : '');
        }

        $short = mb_substr($sql, 0, 60);

        return mb_strlen($sql) > 60 ? $short.'…' : $short;
    }
}
