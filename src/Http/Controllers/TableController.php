<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class TableController
{
    public function __construct(private readonly ConnectionManager $connectionManager) {}

    public function show(Request $request, string $token, string $connection, string $table): View
    {
        $conn      = $this->connectionManager->resolve($connection);
        $inspector = $this->connectionManager->inspector($connection);
        $columns   = $inspector->listColumns($table, $conn);
        $quoted    = $inspector->quoteIdentifier($table);
        $rows      = $conn->select("SELECT * FROM {$quoted} LIMIT 100");
        $rows      = array_map(fn ($r) => (array) $r, $rows);
        $tables    = $inspector->listTables($conn);

        $currentConnection = $connection;

        return view('db-governor::table', compact('table', 'columns', 'rows', 'tables', 'token', 'currentConnection'));
    }
}

