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

    public function table(Request $request, string $token, string $connection, string $table): JsonResponse
    {
        $hidden = config('db-governor.hidden_tables', []);

        if (in_array($table, $hidden, true)) {
            abort(404);
        }

        $conn      = $this->connectionManager->resolve($connection);
        $inspector = $this->connectionManager->inspector($connection);
        $columns   = $inspector->listColumns($table, $conn);

        return response()->json(['columns' => $columns]);
    }
}

