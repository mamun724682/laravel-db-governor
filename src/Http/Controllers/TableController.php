<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Mamun724682\DbGovernor\Drivers\DbInspector;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Enums\QueryType;
use Mamun724682\DbGovernor\Enums\RiskLevel;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class TableController
{
    private const ALLOWED_OPS = ['=', '!=', 'LIKE', 'NOT LIKE', '>', '<', '>=', '<=', 'IS NULL', 'IS NOT NULL', 'IN'];

    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly AccessGuard $guard,
    ) {}

    public function show(Request $request, string $token, string $connection, string $table): View
    {
        $hidden = config('db-governor.hidden_tables', []);

        if (in_array($table, $hidden, strict: true)) {
            abort(404);
        }

        $conn = $this->connectionManager->resolve($connection);
        $inspector = $this->connectionManager->inspector($connection);

        $ttl = config('db-governor.schema_cache_ttl', 300);
        $columns = Cache::remember(
            "db-governor.columns.{$connection}.{$table}",
            $ttl,
            fn () => $inspector->listColumns($table, $conn)
        );

        $quoted = $inspector->quoteIdentifier($table);
        $tables = $this->connectionManager->listTables($connection);

        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));
        $sort = $request->query('sort');
        $dir = $request->query('dir', 'asc') === 'desc' ? 'DESC' : 'ASC';
        $columnNames = array_column($columns, 'name');

        $orderBy = '';
        if ($sort && in_array($sort, $columnNames, strict: true)) {
            $orderBy = 'ORDER BY '.$inspector->quoteIdentifier($sort).' '.$dir;
        }

        [$where, $bindings, $filterGroups] = $this->buildWhere(
            $request->input('f', []),
            $columnNames,
            $inspector
        );

        // Fetch one extra row to detect a next page — no COUNT(*) needed.
        $offset = ($page - 1) * $perPage;
        $rows = array_map(
            fn ($r) => (array) $r,
            $conn->select(
                "SELECT * FROM {$quoted} {$where} {$orderBy} LIMIT ".($perPage + 1)." OFFSET {$offset}",
                $bindings
            )
        );

        // Log filtered browsing as a read entry
        if ($where !== '' && config('db-governor.log_read_queries', true)) {
            $sqlWithValues = $this->bindValuesIntoSql("SELECT * FROM {$quoted} {$where}", $bindings);
            GovernedQuery::create([
                'connection' => $connection,
                'query_table' => $table,
                'name' => $this->nameFromFilterSql($table, $where, $bindings),
                'sql_raw' => $sqlWithValues,
                'query_type' => QueryType::Read->value,
                'status' => QueryStatus::Executed->value,
                'risk_level' => RiskLevel::Low->value,
                'submitted_by' => $this->guard->email(),
                'executed_by' => $this->guard->email(),
                'executed_at' => now(),
                'rows_affected' => max(0, count($rows) - (count($rows) > $perPage ? 1 : 0)),
            ]);
        }

        // Paginator slices to $perPage and sets hasMore = count > $perPage internally.
        $paginator = new Paginator(
            items: $rows,
            perPage: $perPage,
            currentPage: $page,
            options: ['path' => $request->url(), 'query' => $request->except('page')],
        );

        $currentConnection = $connection;

        return view('db-governor::table', compact(
            'table', 'columns', 'paginator', 'filterGroups', 'tables', 'token', 'currentConnection'
        ));
    }

    /**
     * Build a parameterised WHERE clause from the filter request input.
     *
     * @param  array<mixed>  $input
     * @param  array<string>  $columnNames
     * @return array{0: string, 1: array<mixed>, 2: array<mixed>}
     */
    private function buildWhere(array $input, array $columnNames, DbInspector $inspector): array
    {
        $whereParts = [];
        $bindings = [];
        $filterGroups = [];

        foreach ($input as $group) {
            if (! is_array($group)) {
                continue;
            }

            $groupConditions = [];
            $groupFilters = [];

            foreach ($group as $filter) {
                $col = trim((string) ($filter['col'] ?? ''));
                $op = strtoupper(trim((string) ($filter['op'] ?? '=')));
                $val = (string) ($filter['val'] ?? '');

                $groupFilters[] = ['col' => $col, 'op' => $op, 'val' => $val];

                if (! $col || ! in_array($col, $columnNames, strict: true)) {
                    continue;
                }

                if (! in_array($op, self::ALLOWED_OPS, strict: true)) {
                    continue;
                }

                $quotedCol = $inspector->quoteIdentifier($col);

                if ($op === 'IS NULL' || $op === 'IS NOT NULL') {
                    $groupConditions[] = "{$quotedCol} {$op}";
                } elseif ($op === 'IN') {
                    $vals = array_filter(array_map('trim', explode(',', $val)));
                    $placeholders = implode(',', array_fill(0, count($vals), '?'));
                    $groupConditions[] = "{$quotedCol} IN ({$placeholders})";
                    $bindings = array_merge($bindings, array_values($vals));
                } else {
                    $groupConditions[] = "{$quotedCol} {$op} ?";
                    $bindings[] = $val;
                }
            }

            if (! empty($groupFilters)) {
                $filterGroups[] = ['filters' => $groupFilters];
            }

            if (! empty($groupConditions)) {
                $whereParts[] = '('.implode(' AND ', $groupConditions).')';
            }
        }

        if (empty($filterGroups)) {
            $filterGroups = [['filters' => [['col' => '', 'op' => '=', 'val' => '']]]];
        }

        $where = ! empty($whereParts) ? 'WHERE '.implode(' OR ', $whereParts) : '';

        return [$where, $bindings, $filterGroups];
    }

    /**
     * Replace ? placeholders with their bound values for display / audit purposes.
     *
     * @param  array<mixed>  $bindings
     */
    private function bindValuesIntoSql(string $sql, array $bindings): string
    {
        $index = 0;

        return preg_replace_callback('/\?/', function () use (&$index, $bindings): string {
            $val = $bindings[$index++] ?? 'NULL';

            return is_numeric($val) ? (string) $val : "'".addslashes((string) $val)."'";
        }, $sql) ?? $sql;
    }

    /**
     * Generate a human-readable name for a filtered table browse log entry.
     *
     * @param  array<mixed>  $bindings
     */
    private function nameFromFilterSql(string $table, string $where, array $bindings): string
    {
        if ($where === '') {
            return "Browse {$table}";
        }

        $readable = $this->bindValuesIntoSql($where, $bindings);
        $readable = preg_replace('/^WHERE\s+/i', '', $readable) ?? $readable;

        $label = "Browse {$table} WHERE ".$readable;

        return mb_strlen($label) > 120 ? mb_substr($label, 0, 117).'…' : $label;
    }
}
