<?php

namespace Mamun724682\DbGovernor\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mamun724682\DbGovernor\Drivers\DbInspector;
use Mamun724682\DbGovernor\Drivers\MySqlInspector;
use Mamun724682\DbGovernor\Drivers\PgsqlInspector;
use Mamun724682\DbGovernor\Drivers\SqliteInspector;
use Mamun724682\DbGovernor\Exceptions\InvalidConnectionException;

class ConnectionManager
{
    /**
     * @return array<int, string>
     */
    public function allKeys(): array
    {
        return array_keys(config('db-governor.connections', []));
    }

    public function isValidKey(string $key): bool
    {
        return array_key_exists($key, config('db-governor.connections', []));
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return config('db-governor.connections', []);
    }

    public function resolve(string $key): Connection
    {
        if (! $this->isValidKey($key)) {
            throw new InvalidConnectionException(
                "Connection key \"{$key}\" is not configured in db-governor.connections."
            );
        }

        $connectionName = config("db-governor.connections.{$key}");

        return DB::connection($connectionName);
    }

    public function driver(string $key): string
    {
        return $this->resolve($key)->getDriverName();
    }

    public function inspector(Connection $conn): DbInspector
    {
        return match ($conn->getDriverName()) {
            'mysql' => new MySqlInspector,
            'pgsql' => new PgsqlInspector,
            'sqlite' => new SqliteInspector,
            default => throw new InvalidConnectionException(
                "Unsupported database driver \"{$conn->getDriverName()}\"."
            ),
        };
    }

    /**
     * List tables for a connection, excluding any configured in hidden_tables.
     * Results are cached for 5 minutes.
     *
     * @return array<int, string>
     */
    public function listTables(string $key): array
    {
        $ttl = config('db-governor.schema_cache_ttl', 300);
        $cacheKey = "db-governor.tables.{$key}";

        $tables = Cache::remember($cacheKey, $ttl, function () use ($key): array {
            $conn = $this->resolve($key);

            return $this->inspector($conn)->listTables($conn);
        });

        $hidden = config('db-governor.hidden_tables', []);

        return array_values(array_filter(
            $tables,
            fn (string $table) => ! in_array($table, $hidden, strict: true)
        ));
    }

    /**
     * List columns for a table on a connection, cached for schema_cache_ttl seconds.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listColumns(string $key, string $table): array
    {
        $ttl = config('db-governor.schema_cache_ttl', 300);

        return Cache::remember("db-governor.columns.{$key}.{$table}", $ttl, function () use ($key, $table): array {
            $conn = $this->resolve($key);

            return $this->inspector($conn)->listColumns($table, $conn);
        });
    }

    /**
     * Detect child tables that have ON DELETE CASCADE foreign keys pointing to the given target table.
     * Results are cached using the schema_cache_ttl config.
     *
     * @return array<int, string>
     */
    public function detectCascadeTables(string $targetTable, string $key): array
    {
        $ttl = config('db-governor.schema_cache_ttl', 300);
        $cacheKey = "db-governor.cascade.{$key}.{$targetTable}";

        return Cache::remember($cacheKey, $ttl, function () use ($targetTable, $key): array {
            $conn = $this->resolve($key);

            return $this->inspector($conn)->detectCascadeChildTables($targetTable, $conn);
        });
    }

    /**
     * Return the first hidden table name referenced in the SQL, or null if none.
     */
    public function firstHiddenTableIn(string $sql): ?string
    {
        $hidden = config('db-governor.hidden_tables', []);

        foreach ($hidden as $table) {
            if (preg_match('/\b'.preg_quote($table, '/').'\\b/i', $sql)) {
                return $table;
            }
        }

        return null;
    }
}
