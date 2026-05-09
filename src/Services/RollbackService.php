<?php

namespace Mamun724682\DbGovernor\Services;

use Mamun724682\DbGovernor\DTOs\RollbackResult;
use Mamun724682\DbGovernor\DTOs\SnapshotData;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;

class RollbackService
{
    /** @var array<int, string> */
    private const SKIP_VERBS = ['INSERT', 'CREATE', 'ALTER', 'DROP', 'TRUNCATE'];

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly QueryClassifier $classifier,
        private readonly AccessGuard $guard,
    ) {}

    public function captureBeforeState(string $sql, string $connectionKey): ?SnapshotData
    {
        $verb = strtoupper($this->classifier->extractVerb($sql));

        if (in_array($verb, self::SKIP_VERBS, strict: true)) {
            return null;
        }

        if (! preg_match('/\bWHERE\b/i', $sql)) {
            return null;
        }

        $tables = $this->classifier->extractTables($sql);

        if (empty($tables)) {
            return null;
        }

        $table = $tables[0];

        try {
            $conn = $this->connectionManager->resolve($connectionKey);
            $inspector = $this->connectionManager->inspector($connectionKey);
            $primaryKey = $inspector->detectPrimaryKey($table, $conn);
            $maxRows = (int) config('db-governor.snapshot_max_rows', 500);

            // Extract WHERE clause (exclude any trailing LIMIT/ORDER BY/etc.)
            if (! preg_match('/\bWHERE\b(.+?)(?:\b(?:ORDER\s+BY|LIMIT|GROUP\s+BY|HAVING)\b|$)/is', $sql, $m)) {
                return null;
            }

            $where = trim($m[1]);
            // Note: $where is extracted from $sql which is an admin-approved, stored query.
            // It has been reviewed before execution, so the risk profile here is lower
            // than raw user input. Parameterizing an arbitrary WHERE clause is not possible
            // in standard SQL; no further mitigation is applied.
            $rows = $conn->select('SELECT * FROM '.$inspector->quoteIdentifier($table).' WHERE '.$where);

            if (empty($rows) || count($rows) > $maxRows) {
                return null;
            }

            return new SnapshotData(
                strategy: 'row_snapshot',
                tableName: $table,
                rows: array_map(fn ($r) => (array) $r, $rows),
                primaryKey: $primaryKey,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function rollback(GovernedQuery $query): RollbackResult
    {
        if (empty($query->snapshot_data)) {
            return new RollbackResult(success: false, message: 'No snapshot data available for rollback.');
        }

        if ($query->rolled_back_at !== null) {
            return new RollbackResult(success: false, message: 'Already rolled back.');
        }

        try {
            $rows = json_decode($query->snapshot_data, true) ?? [];
            $conn = $this->connectionManager->resolve($query->connection);
            $inspector = $this->connectionManager->inspector($query->connection);
            $table = $query->query_table;
            $pk = $query->snapshot_primary_key ?? 'id';
            $verb = strtoupper($this->classifier->extractVerb($query->sql_raw));
            $count = 0;
            $rollbackSqlParts = [];

            $conn->transaction(function () use ($conn, $inspector, $table, $pk, $rows, $verb, &$count, &$rollbackSqlParts) {
                foreach ($rows as $row) {
                    if ($verb === 'DELETE') {
                        // Re-insert deleted rows
                        $cols = array_keys($row);
                        $colList = implode(', ', array_map(fn ($c) => $inspector->quoteIdentifier($c), $cols));
                        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                        $bindings = array_values($row);
                        $qt = $inspector->quoteIdentifier($table);

                        $conn->statement(
                            "INSERT INTO {$qt} ({$colList}) VALUES ({$placeholders})",
                            $bindings
                        );

                        $vals = implode(', ', array_map(fn ($v) => is_null($v) ? 'NULL' : (is_numeric($v) ? $v : "'".addslashes((string) $v)."'"), $bindings));
                        $rollbackSqlParts[] = "INSERT INTO {$qt} ({$colList}) VALUES ({$vals})";
                    } else {
                        // Restore updated rows
                        $pkValue = $row[$pk];
                        $setClauses = [];
                        $bindings = [];

                        foreach ($row as $col => $value) {
                            if ($col === $pk) {
                                continue;
                            }
                            $setClauses[] = $inspector->quoteIdentifier($col).' = ?';
                            $bindings[] = $value;
                        }

                        if (empty($setClauses)) {
                            continue;
                        }

                        $qt = $inspector->quoteIdentifier($table);
                        $qpk = $inspector->quoteIdentifier($pk);
                        $bindings[] = $pkValue;
                        $conn->update(
                            "UPDATE {$qt} SET ".implode(', ', $setClauses)." WHERE {$qpk} = ?",
                            $bindings
                        );

                        $setParts = [];
                        foreach ($row as $col => $value) {
                            if ($col === $pk) {
                                continue;
                            }
                            $val = is_null($value) ? 'NULL' : (is_numeric($value) ? $value : "'".addslashes((string) $value)."'");
                            $setParts[] = $inspector->quoteIdentifier($col)." = {$val}";
                        }
                        $pkValFormatted = is_numeric($pkValue) ? $pkValue : "'".addslashes((string) $pkValue)."'";
                        $rollbackSqlParts[] = "UPDATE {$qt} SET ".implode(', ', $setParts)." WHERE {$qpk} = {$pkValFormatted}";
                    }

                    $count++;
                }
            });

            $query->update([
                'status' => QueryStatus::RolledBack->value,
                'rolled_back_by' => $this->guard->email(),
                'rolled_back_at' => now(),
                'rollback_sql' => implode(";\n", $rollbackSqlParts),
                'rollback_error' => null,
            ]);

            return new RollbackResult(success: true, rowsRestored: $count);
        } catch (\Throwable $e) {
            $query->update(['rollback_error' => $e->getMessage()]);

            return new RollbackResult(success: false, message: $e->getMessage());
        }
    }
}
