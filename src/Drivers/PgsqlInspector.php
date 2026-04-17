<?php

namespace Mamun724682\DbGovernor\Drivers;

use Illuminate\Database\Connection;

class PgsqlInspector implements DbInspector
{
    public function quoteIdentifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    public function detectPrimaryKey(string $table, Connection $conn): string
    {
        $rows = $conn->select(
            "SELECT kcu.column_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema    = kcu.table_schema
            WHERE tc.constraint_type = 'PRIMARY KEY'
              AND tc.table_schema    = 'public'
              AND tc.table_name      = ?
            ORDER BY kcu.ordinal_position
            LIMIT 1",
            [$table]
        );

        if (! empty($rows)) {
            return $rows[0]->column_name;
        }

        return 'id';
    }

    /**
     * @return array<int, string>
     */
    public function listTables(Connection $conn): array
    {
        $rows = $conn->select(
            "SELECT table_name
             FROM information_schema.tables
             WHERE table_schema = 'public'
               AND table_type   = 'BASE TABLE'
             ORDER BY table_name"
        );

        return array_column(array_map(fn ($r) => (array) $r, $rows), 'table_name');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listColumns(string $table, Connection $conn): array
    {
        $rows = $conn->select(
            "SELECT column_name, data_type
             FROM information_schema.columns
             WHERE table_name   = ?
               AND table_schema = 'public'
             ORDER BY ordinal_position",
            [$table]
        );

        return array_map(fn ($row) => [
            'name' => $row->column_name,
            'type' => $row->data_type,
        ], $rows);
    }

    public function estimateAffectedRows(string $sql, Connection $conn): ?int
    {
        $rows = $conn->select("EXPLAIN {$sql}");

        foreach ($rows as $row) {
            $line = array_values((array) $row)[0];
            if (preg_match('/rows=(\d+)/i', (string) $line, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }
}
