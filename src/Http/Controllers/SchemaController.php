<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class SchemaController
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
    ) {}

    public function cascadeCheck(Request $request, string $connection): JsonResponse
    {
        $table = (string) $request->query('table', '');

        if ($table === '') {
            return response()->json(['cascade_tables' => []]);
        }

        $hidden = config('db-governor.hidden_tables', []);

        if (in_array($table, $hidden, true)) {
            return response()->json(['cascade_tables' => []]);
        }

        $cascadeTables = $this->connectionManager->detectCascadeTables($table, $connection);

        return response()->json(['cascade_tables' => $cascadeTables]);
    }

    public function table(Request $request, string $connection, string $table): JsonResponse
    {
        $hidden = config('db-governor.hidden_tables', []);

        if (in_array($table, $hidden, true)) {
            abort(404);
        }

        $ttl = config('db-governor.schema_cache_ttl', 300);
        $cacheKey = "db-governor.columns.{$connection}.{$table}";

        $columns = Cache::remember($cacheKey, $ttl, function () use ($connection, $table): array {
            $conn = $this->connectionManager->resolve($connection);
            $inspector = $this->connectionManager->inspector($connection);

            return $inspector->listColumns($table, $conn);
        });

        return response()->json(['columns' => $columns]);
    }
}
