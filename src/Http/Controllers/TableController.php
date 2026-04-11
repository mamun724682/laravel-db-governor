<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class TableController
{
    public function __construct(private readonly ConnectionManager $connectionManager) {}

    public function show(Request $request, string $token, string $connection, string $table): View
    {
        $hidden = config('db-governor.hidden_tables', []);

        if (in_array($table, $hidden, strict: true)) {
            abort(404);
        }

        $conn      = $this->connectionManager->resolve($connection);
        $inspector = $this->connectionManager->inspector($connection);
        $columns   = $inspector->listColumns($table, $conn);
        $quoted    = $inspector->quoteIdentifier($table);
        $tables    = $this->connectionManager->listTables($connection);

        $perPage     = 25;
        $page        = max(1, (int) $request->query('page', 1));
        $sort        = $request->query('sort');
        $dir         = $request->query('dir', 'asc') === 'desc' ? 'DESC' : 'ASC';
        $columnNames = array_column($columns, 'name');

        $orderBy = '';
        if ($sort && in_array($sort, $columnNames, strict: true)) {
            $orderBy = 'ORDER BY '.$inspector->quoteIdentifier($sort).' '.$dir;
        }

        // Fetch one extra row to detect a next page — no COUNT(*) needed.
        $offset = ($page - 1) * $perPage;
        $rows   = array_map(
            fn ($r) => (array) $r,
            $conn->select("SELECT * FROM {$quoted} {$orderBy} LIMIT ".($perPage + 1)." OFFSET {$offset}")
        );

        // Paginator slices to $perPage and sets hasMore = count > $perPage internally.
        $paginator = new Paginator(
            items: $rows,
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->except('page')],
        );

        $currentConnection = $connection;

        return view('db-governor::table', compact('table', 'columns', 'paginator', 'tables', 'token', 'currentConnection'));
    }
}

