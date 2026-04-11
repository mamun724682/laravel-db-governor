<?php

namespace Mamun724682\DbGovernor\Drivers;

use Illuminate\Database\Connection;

interface DbInspector
{
    public function quoteIdentifier(string $name): string;

    public function detectPrimaryKey(string $table, Connection $conn): string;

    /**
     * @return array<int, string>
     */
    public function listTables(Connection $conn): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listColumns(string $table, Connection $conn): array;

    public function estimateAffectedRows(string $sql, Connection $conn): ?int;
}

