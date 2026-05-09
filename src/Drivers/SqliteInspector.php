<?php

namespace Mamun724682\DbGovernor\Drivers;

use Illuminate\Database\Connection;

class SqliteInspector implements DbInspector
{
    public function quoteIdentifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    public function detectPrimaryKey(string $table, Connection $conn): string
    {
        $rows = $conn->select("PRAGMA table_info(\"{$table}\")");

        foreach ($rows as $row) {
            if ((int) $row->pk >= 1) {
                return $row->name;
            }
        }

        return 'id';
    }

    /**
     * @return array<int, string>
     */
    public function listTables(Connection $conn): array
    {
        $rows = $conn->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );

        return array_column(array_map(fn ($r) => (array) $r, $rows), 'name');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listColumns(string $table, Connection $conn): array
    {
        $rows = $conn->select("PRAGMA table_info(\"{$table}\")");

        return array_map(fn ($row) => [
            'name' => $row->name,
            'type' => $row->type,
            'required' => (int) $row->notnull === 1
                && $row->dflt_value === null
                && (int) $row->pk === 0,
        ], $rows);
    }

    public function estimateAffectedRows(string $sql, Connection $conn): ?int
    {
        // SQLite has no EXPLAIN equivalent for DML row estimation.
        // Returning null tells callers to skip row-count checks for write queries.
        return null;
    }

    /**
     * SQLite has no cross-table FK metadata query; iterate via PRAGMA.
     *
     * @return array<int, string>
     */
    public function detectCascadeChildTables(string $targetTable, Connection $conn): array
    {
        $result = [];

        foreach ($this->listTables($conn) as $table) {
            if ($table === $targetTable) {
                continue;
            }

            try {
                $fks = $conn->select("PRAGMA foreign_key_list(\"{$table}\")");

                foreach ($fks as $fk) {
                    if ($fk->table === $targetTable && strtolower($fk->on_delete) === 'cascade') {
                        $result[] = $table;
                        break;
                    }
                }
            } catch (\Throwable) {
                // Skip tables we cannot inspect
            }
        }

        return $result;
    }
}
