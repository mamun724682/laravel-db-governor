# laravel-db-governor

> Production-safety database governance layer for Laravel — query classification, risk analysis, human approval workflows, snapshot-based rollback, and a zero-dependency web UI.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.4 |
| Laravel | ^13 |

---

## Installation

```bash
composer require mamun724682/laravel-db-governor
```

The package auto-discovers its service provider. No manual registration needed.

### Publish & Migrate

```bash
# Publish the config file
php artisan vendor:publish --tag=db-governor-config

# Publish the Blade views (optional — for customisation)
php artisan vendor:publish --tag=db-governor-views

# Run the governance table migration
php artisan migrate
```

---

## Configuration

After publishing, edit `config/db-governor.php`. Every option can be set via `.env`.

| `.env` key | Config key | Default | Description |
|---|---|---|---|
| `DB_GOVERNOR_PATH` | `path` | `db-governor` | URL prefix for the web UI |
| `DB_GOVERNOR_TABLE` | `table_name` | `dbg_queries` | Table that stores governed queries |
| `DB_GOVERNOR_TOKEN_EXPIRY` | `token_expiry_hours` | `8` | Hours a login token stays valid |
| `DB_GOVERNOR_ADMINS` | `allowed.admins` | _(empty)_ | Comma-separated admin emails |
| `DB_GOVERNOR_EMPLOYEES` | `allowed.employees` | _(empty)_ | Comma-separated employee emails |
| `DB_GOVERNOR_CONNECTION_KEY` | _(connections map key)_ | `main` | URL slug for the default connection |
| `DB_GOVERNOR_CONNECTION` | _(connections map value)_ | `mysql` | Laravel DB connection name |
| `DB_GOVERNOR_MAX_ROWS` | `max_affected_rows` | `1000` | Row threshold that escalates risk to High |
| `DB_GOVERNOR_DRY_RUN` | `dry_run_enabled` | `true` | Enable EXPLAIN-based row estimation |
| `DB_GOVERNOR_ROLLBACK` | `rollback_strategy` | `row_snapshot` | `row_snapshot` \| `generated_sql` \| `none` |
| `DB_GOVERNOR_SNAPSHOT_MAX` | `snapshot_max_rows` | `500` | Max rows captured for rollback snapshot |
| `DB_GOVERNOR_STORAGE_CONNECTION` | `governance_connection` | `null` | Separate DB connection for `dbg_queries` table (null = app default) |

### Minimal `.env` example

```dotenv
DB_GOVERNOR_ADMINS=alice@company.com,bob@company.com
DB_GOVERNOR_EMPLOYEES=dev1@company.com,dev2@company.com
DB_GOVERNOR_CONNECTION_KEY=prod
DB_GOVERNOR_CONNECTION=mysql
```

---

## Multi-Connection Setup

To govern multiple databases, publish the config and edit `config/db-governor.php`:

```php
'connections' => [
    'prod'    => 'mysql',          // maps URL slug → Laravel DB connection name
    'replica' => 'mysql_read',
    'legacy'  => 'pgsql',
    'reports' => 'sqlite',
],
```

Each connection gets its own dashboard, table browser, and query log at:

```
/{prefix}/{token}/{connection}/
```

---

## Authentication Flow

DB Governor uses **stateless token auth** — no session dependency.

1. User visits `/{prefix}/login` and submits their email.
2. If the email is in `allowed.admins` or `allowed.employees`, a signed, time-limited token is issued.
3. The token is embedded in every subsequent URL (e.g., `/{prefix}/{token}/{connection}/`).
4. The `DbGovernanceAccess` middleware validates the token on every protected request — checking signature, expiry, and that the email is still in the config.

Tokens expire after `token_expiry_hours` hours (default: 8). Expired or tampered tokens redirect to login.

---

## Role Model

| Action | Admin | Employee |
|---|---|---|
| Submit a READ query (executes immediately) | ✅ | ✅ |
| Submit a WRITE / DDL query (goes to queue) | ✅ | ✅ |
| Approve a pending query | ✅ | ❌ |
| Reject a pending query | ✅ | ❌ |
| Execute an approved query | ✅ | ❌ |
| Rollback an executed query | ✅ | ❌ |
| View all queries | ✅ | ✅ (own only) |

---

## Risk Levels

| Level | Trigger |
|---|---|
| `low` | Default for READ queries |
| `medium` | Default for WRITE / DDL queries |
| `high` | Matches a flagged pattern **or** estimated rows > `max_affected_rows` |
| `critical` | Matches a blocked pattern — query is immediately rejected |

### Blocked patterns (default)

- `DROP TABLE`, `DROP DATABASE`, `DROP SCHEMA`
- `TRUNCATE`

### Flagged patterns (default)

- `UPDATE … SET` without a `WHERE` clause
- `DELETE FROM` without a `WHERE` clause

Both lists are fully configurable regex arrays in `config/db-governor.php`.

---

## Driver Compatibility Matrix

| Feature | MySQL | PostgreSQL | SQLite |
|---|---|---|---|
| List tables | ✅ `SHOW TABLES` | ✅ `information_schema` | ✅ `sqlite_master` |
| List columns | ✅ `SHOW COLUMNS` | ✅ `information_schema.columns` | ✅ `PRAGMA table_info` |
| List indexes | ✅ `SHOW KEYS` | ✅ `information_schema.statistics` | ✅ `PRAGMA index_list` |
| Row estimation (dry-run) | ✅ `EXPLAIN` rows field | ✅ `EXPLAIN` rows field | ✅ `COUNT(*)` on table |
| Snapshot rollback | ✅ | ✅ | ✅ |

---

## Adding a New Driver

1. Implement `Mamun724682\DbGovernor\Drivers\DbInspector`:

```php
class MyDriver implements DbInspector
{
    public function listTables(Connection $connection): array { … }
    public function listColumns(string $table, Connection $connection): array { … }
    public function listIndexes(string $table, Connection $connection): array { … }
    public function estimateAffectedRows(string $sql, Connection $connection): ?int { … }
    public function driverName(): string { return 'mydriver'; }
}
```

2. Register it in `ConnectionManager::inspector()`:

```php
return match ($driver) {
    'mysql'    => new MySqlInspector(),
    'pgsql'    => new PgsqlInspector(),
    'sqlite'   => new SqliteInspector(),
    'mydriver' => new MyDriver(),
    default    => throw new InvalidConnectionException("No inspector for driver: {$driver}"),
};
```

---

## Security Notes

- **Token expiry** — tokens are signed with `APP_KEY` via Laravel's `Crypt` facade and expire after a configurable number of hours.
- **Email re-validation** — every protected request re-checks that the token's email is still present in `allowed.admins` or `allowed.employees`. Removing an email from config immediately revokes access on the next request.
- **URL-safe tokens** — tokens use URL-safe base64 encoding so they can be embedded in route segments without encoding issues.
- **No session dependency** — the package does not rely on Laravel's session system, making it safe to use on stateless infrastructure.
- **Separate governance connection** — use `governance_connection` to store the `dbg_queries` table on a different database from the one being governed.

---

## License

MIT

