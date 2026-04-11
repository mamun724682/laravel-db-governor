<?php

namespace Mamun724682\DbGovernor\Services;

use Illuminate\Database\Connection;
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

    public function inspector(string $key): DbInspector
    {
        return match ($this->driver($key)) {
            'mysql'  => new MySqlInspector(),
            'pgsql'  => new PgsqlInspector(),
            'sqlite' => new SqliteInspector(),
            default  => throw new InvalidConnectionException(
                "Unsupported database driver for connection key \"{$key}\"."
            ),
        };
    }

    /**
     * List tables for a connection, excluding any configured in hidden_tables.
     *
     * @return array<int, string>
     */
    public function listTables(string $key): array
    {
        $tables = $this->inspector($key)->listTables($this->resolve($key));
        $hidden = config('db-governor.hidden_tables', []);

        return array_values(array_filter(
            $tables,
            fn (string $table) => ! in_array($table, $hidden, strict: true)
        ));
    }
}

