<?php

namespace Mamun724682\DbGovernor\Drivers;

use Illuminate\Database\Connection;

class MySqlInspector implements DbInspector
{
    public function quoteIdentifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    public function detectPrimaryKey(string $table, Connection $conn): string
    {
        $quoted = $this->quoteIdentifier($table);
        $rows = $conn->select(
            "SHOW KEYS FROM {$quoted} WHERE Key_name = 'PRIMARY'"
        );

        if (! empty($rows)) {
            return $rows[0]->Column_name;
        }

        return 'id';
    }

    /**
     * @return array<int, string>
     */
    public function listTables(Connection $conn): array
    {
        $rows = $conn->select('SHOW TABLES');

        return array_map(fn ($row) => array_values((array) $row)[0], $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listColumns(string $table, Connection $conn): array
    {
        $quoted = $this->quoteIdentifier($table);
        $rows = $conn->select("SHOW COLUMNS FROM {$quoted}");

        return array_map(fn ($row) => [
            'name' => $row->Field,
            'type' => $row->Type,
        ], $rows);
    }

    public function estimateAffectedRows(string $sql, Connection $conn): ?int
    {
        $rows = $conn->select("EXPLAIN {$sql}");

        if (empty($rows)) {
            return null;
        }

        return (int) array_sum(array_column(
            array_map(fn ($r) => (array) $r, $rows),
            'rows'
        ));
    }
}
