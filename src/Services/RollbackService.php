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
            $q = $conn->getDriverName() === 'mysql' ? '`' : '"';
            $rows = $conn->select("SELECT * FROM {$q}{$table}{$q} WHERE {$where}");

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
            $table = $query->snapshot_table;
            $pk = $query->snapshot_primary_key ?? 'id';
            $q = $conn->getDriverName() === 'mysql' ? '`' : '"';
            $count = 0;

            $conn->transaction(function () use ($conn, $table, $pk, $rows, $q, &$count) {
                foreach ($rows as $row) {
                    $pkValue = $row[$pk];
                    $setClauses = [];
                    $bindings = [];

                    foreach ($row as $col => $value) {
                        if ($col === $pk) {
                            continue;
                        }
                        $setClauses[] = "{$q}{$col}{$q} = ?";
                        $bindings[] = $value;
                    }

                    if (empty($setClauses)) {
                        continue;
                    }

                    $bindings[] = $pkValue;
                    $conn->update(
                        "UPDATE {$q}{$table}{$q} SET ".implode(', ', $setClauses)." WHERE {$q}{$pk}{$q} = ?",
                        $bindings
                    );
                    $count++;
                }
            });

            $query->update([
                'status' => QueryStatus::RolledBack->value,
                'rolled_back_by' => $this->guard->email(),
                'rolled_back_at' => now(),
                'rollback_error' => null,
            ]);

            return new RollbackResult(success: true, rowsRestored: $count);
        } catch (\Throwable $e) {
            $query->update(['rollback_error' => $e->getMessage()]);

            return new RollbackResult(success: false, message: $e->getMessage());
        }
    }
}
