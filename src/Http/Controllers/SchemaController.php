<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $columns = $this->connectionManager->listColumns($connection, $table);

        return response()->json(['columns' => $columns]);
    }
}
