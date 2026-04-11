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
        ], $rows);
    }

    public function estimateAffectedRows(string $sql, Connection $conn): ?int
    {
        $trimmed = trim($sql);

        if (! preg_match('/\bWHERE\b/i', $trimmed)) {
            return null;
        }

        // UPDATE table SET ... WHERE condition
        if (preg_match('/^\s*UPDATE\s+(\w+)\s+SET\s+.+?\s+WHERE\s+(.+)$/is', $trimmed, $m)) {
            $result = $conn->selectOne(
                "SELECT COUNT(*) as cnt FROM \"{$m[1]}\" WHERE {$m[2]}"
            );

            return (int) $result->cnt;
        }

        // DELETE FROM table WHERE condition
        if (preg_match('/^\s*DELETE\s+FROM\s+(\w+)\s+WHERE\s+(.+)$/is', $trimmed, $m)) {
            $result = $conn->selectOne(
                "SELECT COUNT(*) as cnt FROM \"{$m[1]}\" WHERE {$m[2]}"
            );

            return (int) $result->cnt;
        }

        return null;
    }
}

