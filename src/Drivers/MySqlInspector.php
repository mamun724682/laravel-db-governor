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
            'required' => $row->Null === 'NO'
                && $row->Default === null
                && ! str_contains(strtolower((string) $row->Extra), 'auto_increment'),
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

    /**
     * @return array<int, string>
     */
    public function detectCascadeChildTables(string $targetTable, Connection $conn): array
    {
        $rows = $conn->select(
            "SELECT DISTINCT kcu.TABLE_NAME
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON rc.CONSTRAINT_NAME    = kcu.CONSTRAINT_NAME
              AND rc.CONSTRAINT_SCHEMA  = kcu.CONSTRAINT_SCHEMA
             WHERE rc.DELETE_RULE              = 'CASCADE'
               AND kcu.REFERENCED_TABLE_NAME   = ?
               AND rc.CONSTRAINT_SCHEMA        = DATABASE()",
            [$targetTable]
        );

        return array_column(array_map(fn ($r) => (array) $r, $rows), 'TABLE_NAME');
    }
}
