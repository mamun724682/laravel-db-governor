# Laravel DB Governor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mamun724682/laravel-db-governor.svg?style=flat-square)](https://packagist.org/packages/mamun724682/laravel-db-governor)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue?style=flat-square)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10%20–%2013-red?style=flat-square)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/mamun724682/laravel-db-governor.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-pest-green?style=flat-square)](https://pestphp.com)

**A production-safety database governance layer for Laravel teams.**
Intercept, review, approve, execute, and roll back SQL queries through a secure web UI — with full audit trails, risk scoring, and row-level snapshots.

---

## Why DB Governor?

Running raw `UPDATE` or `DELETE` queries directly on a production database is dangerous. One wrong `WHERE` clause (or no clause at all) can silently wipe data. **DB Governor** forces every write query through a structured approval workflow before it ever touches your database.

- ✅ Employees submit queries → Admins review & approve → Execution is controlled
- ✅ Every query is scored for risk and flagged for dangerous patterns
- ✅ Before execution, row-level snapshots are captured for instant rollback
- ✅ Full audit log with submitter, reviewer, executor identity and timestamps
- ✅ Works across **multiple database connections** from a single UI

---

## Features

### 🔐 Role-Based Access
Two roles configured entirely via environment variables — no extra tables required.

| Role | Capabilities |
|---|---|
| **Admin** | Approve / Reject / Execute / Rollback, view all queries from all users |
| **Employee** | Submit queries, view own submissions; SELECTs execute immediately |

Token-based sessions (stored in cache) with configurable expiry (`DB_GOVERNOR_TOKEN_EXPIRY`). Role is re-derived on every request so promotions/demotions take effect without re-login.

---

### 📋 Query Approval Workflow

```
Submit → [pending] → Approve → [approved] → Execute → [executed]
                   ↘ Reject → [rejected]            ↘ Rollback → [rolled_back]
```

Every query record stores:
- Raw SQL, query type (READ / WRITE / DDL), affected table
- Submitter email + IP, submission timestamp
- Reviewer email, review timestamp, review note
- Executor email, execution timestamp, rows affected, execution time (ms)
- Execution error (if any) — surfaced immediately in the UI

---

### ⚠️ Automatic Risk Scoring

Every submitted query is analysed before it enters the queue:

| Risk Level | Trigger |
|---|---|
| `critical` | Matches a **blocked pattern** (e.g. `DROP TABLE`, `TRUNCATE`) — query is blocked outright |
| `high` | Matches a **flagged pattern** (e.g. `UPDATE … SET` without `WHERE`) or estimated rows exceed `max_affected_rows` |
| `medium` / `low` | Everything else |

Blocked/flagged patterns are fully customisable regex arrays in the config. A dry-run engine estimates affected row counts before submission.

---

### ↩️ Row-Level Rollback

Before executing any `UPDATE` or `DELETE`, DB Governor captures a **before-state snapshot** of every affected row. If something goes wrong after execution, a one-click Rollback:

- Re-inserts deleted rows (via `INSERT`)
- Restores updated columns to their original values (via `UPDATE … WHERE pk = ?`)
- Wraps everything in a single database transaction

Snapshots are stored as JSON in the audit record. Configurable via `snapshot_max_rows` (default: 500 rows).

---

### 🖥️ SQL Console

A full in-browser SQL console with two modes:

**Query Builder** (visual, no SQL knowledge needed)
- `SELECT` — pick columns, add WHERE / ORDER BY / LIMIT
- `INSERT` — all non-`id` columns pre-filled; required fields (NOT NULL, no default) are marked with `*` and locked
- `UPDATE` — add/remove SET columns with WHERE condition
- `DELETE` — WHERE condition with danger warning

**Raw SQL** — free-form textarea with:
- SQL keyword + table + column autocomplete
- Arrow-key navigation through suggestions

Value inputs adapt to column type automatically:
- `date` / `datetime` / `timestamp` → native date/time pickers
- `json` → resizable monospace textarea
- Everything else → plain text input

---

### 📊 Table Browser

Browse any table directly in the UI:
- Paginated rows (25 per page), sortable column headers
- Advanced filter builder with AND / OR groups and operators (`=`, `!=`, `LIKE`, `NOT LIKE`, `>`, `<`, `>=`, `<=`, `IN`, `IS NULL`, `IS NOT NULL`)
- Smart value inputs in filters (same date-picker / JSON textarea logic as the query builder)
- All filtered browse actions are logged as read queries for the audit trail

---

### 🗂️ Sidebar Navigation

- Expandable table list — click the chevron to reveal columns inline
- Column list shows name (monospace), type, and a **red dot** for required (NOT NULL, no default) vs **grey dot** for optional
- Live search to filter tables by name

---

### 🔍 Query Log

- Filterable by status, query type, keyword, date range, and submitter (admins only)
- Tabbed view: Write Queries / Read Queries
- Click any row to open a detail modal with full SQL, risk flags, review details, execution details, snapshot data, and rollback info

---

### ⚡ Schema Cache

Table list and column metadata are cached (default: 5 minutes) to avoid repeated introspection queries. Configurable via `DB_GOVERNOR_SCHEMA_CACHE_TTL`.

---

### 🔌 Multi-Connection Support

Map multiple Laravel database connections to named URL slugs:

```php
'connections' => [
    'main'    => 'mysql',
    'replica' => 'mysql_read',
    'legacy'  => 'pgsql',
],
```

Switch between connections from the UI. Each connection has its own table browser, query log, and SQL console.

---

### 🛡️ Hidden Tables

Framework-internal tables (`migrations`, `cache`, `sessions`, `jobs`, `telescope_*`, etc.) are hidden from the UI by default and blocked from query execution.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | `^8.1` |
| Laravel | `^10 \| ^11 \| ^12 \| ^13` |
| Database | MySQL, PostgreSQL, SQLite |

---

## Installation

```bash
composer require mamun724682/laravel-db-governor
```

Publish the config and run migrations:

```bash
php artisan vendor:publish --tag=db-governor-config
php artisan migrate
```

Optionally publish views to customise the UI:

```bash
php artisan vendor:publish --tag=db-governor-views
```

---

## Configuration

Add to your `.env`:

```dotenv
# Who can access the UI
DB_GOVERNOR_ADMINS=admin@company.com,dba@company.com
DB_GOVERNOR_EMPLOYEES=dev1@company.com,dev2@company.com

# Which Laravel DB connection to govern
DB_GOVERNOR_CONNECTION_KEY=main
DB_GOVERNOR_CONNECTION=mysql

# Optional tweaks
DB_GOVERNOR_PATH=db-governor          # URL prefix
DB_GOVERNOR_TOKEN_EXPIRY=8            # session hours
DB_GOVERNOR_MAX_ROWS=1000             # high-risk row threshold
DB_GOVERNOR_DRY_RUN=true              # enable row estimation
DB_GOVERNOR_ROLLBACK=row_snapshot     # row_snapshot | generated_sql | none
DB_GOVERNOR_SNAPSHOT_MAX=500          # max rows to snapshot
DB_GOVERNOR_SCHEMA_CACHE_TTL=300      # schema cache in seconds (0 = off)
DB_GOVERNOR_LOG_READS=true            # log SELECT queries for audit
DB_GOVERNOR_STORAGE_CONNECTION=null   # separate DB for the governance table
```

Full config reference at `config/db-governor.php` after publishing.

---

## Usage

Navigate to `http://your-app.test/db-governor` and log in with an admin or employee email.

### Workflow example

```
1. Employee logs in → opens SQL Console → builds INSERT query visually
2. Clicks "Generate SQL →" → reviews raw SQL → submits with a description
3. Admin logs in → sees pending query in Query Log → reviews SQL and risk flags
4. Admin approves with a note → clicks Execute → rows affected shown immediately
5. Something went wrong? → Admin clicks Rollback → rows restored from snapshot
```

---

## Customising Risk Patterns

```php
// config/db-governor.php

// Block outright (status = blocked, never queued)
'blocked_patterns' => [
    '/^\s*DROP\s+(TABLE|DATABASE|SCHEMA)/i',
    '/^\s*TRUNCATE\s+/i',
],

// Flag as high risk (queued but prominently warned)
'flagged_patterns' => [
    '/UPDATE\s+\w[\w.]*\s+SET(?!.*\bWHERE\b)/is',  // UPDATE without WHERE
    '/DELETE\s+FROM\s+\w[\w.]*\s*(?!WHERE)/is',      // DELETE without WHERE
],
```

---

## Multiple Connections

```php
'connections' => [
    'prod'    => env('DB_PROD_CONNECTION', 'mysql'),
    'staging' => env('DB_STAGING_CONNECTION', 'mysql_staging'),
    'legacy'  => 'pgsql_legacy',
],
```

Each connection gets its own URL: `/db-governor/{token}/prod`, `/db-governor/{token}/staging`, etc.

---

## Security

- Sessions use random 32-character tokens stored in the **cache** (not cookies or session table)
- Token expiry is configurable; tokens are invalidated immediately on logout
- Role is re-checked on every request — revoking access in config takes immediate effect without re-login
- All write query execution is gated behind the approval workflow
- Hidden tables are enforced both in the UI and at the query execution layer

---

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with coverage
composer test:coverage

# Check code style
composer lint

# Fix code style
composer lint:fix
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

---

## Contributing

Pull requests are welcome. Please open an issue first to discuss significant changes.

1. Fork the repository
2. Create a feature branch (`git checkout -b feat/my-feature`)
3. Write tests for your changes
4. Ensure `composer test` and `composer lint` both pass
5. Submit a pull request

---

## Support

If DB Governor helps your team, please star the repository.

---

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.

