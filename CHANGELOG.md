# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.0.0] - 2026-04-20

### Added

#### Core Workflow
- Query approval workflow: `pending → approved → executed` with `reject` and `rolled_back` states
- Role-based access: **admin** (approve / reject / execute / rollback / view all) and **employee** (submit / view own)
- Token-based sessions stored in cache with configurable expiry (`DB_GOVERNOR_TOKEN_EXPIRY`)
- Role re-derived on every request — access changes take effect without re-login

#### Risk Analysis
- Automatic risk scoring on every submitted query: `low`, `medium`, `high`, `critical`
- Configurable **blocked patterns** (query blocked outright, never queued)
- Configurable **flagged patterns** (queued but marked high risk)
- Dry-run row estimation to flag queries exceeding `max_affected_rows`
- Pre-submission WHERE-row validation for UPDATE / DELETE — rejects queries with no matching rows

#### Query Execution & Rollback
- Controlled query execution gated behind the approval workflow
- Row-level **snapshot** captured before every UPDATE / DELETE (up to `snapshot_max_rows`)
- One-click rollback: re-inserts deleted rows or restores updated values, wrapped in a transaction
- Execution errors surfaced immediately in the UI (no silent success)

#### SQL Console
- **Query Builder** tab with visual SELECT / INSERT / UPDATE / DELETE builders
- INSERT: all non-`id` columns pre-filled; required fields (NOT NULL, no default) marked with `*` and locked
- INSERT / UPDATE: add / remove column rows with `+` / `✕` buttons
- Raw SQL tab with keyword + table + column autocomplete and arrow-key navigation
- Smart value inputs: date/datetime/timestamp → native date-pickers; json → resizable textarea
- `generateSql()` normalises `datetime-local` values (`T` separator) to SQL format before quoting

#### Table Browser
- Paginated table viewer (25 rows/page) with sortable column headers
- Advanced filter builder: AND / OR groups, operators `=`, `!=`, `LIKE`, `NOT LIKE`, `>`, `<`, `>=`, `<=`, `IN`, `IS NULL`, `IS NOT NULL`
- Smart filter value inputs matching column type (date-picker, JSON textarea, plain text)
- Filtered browse actions logged as read queries for audit

#### Sidebar
- Expandable table list with chevron toggle — columns lazy-loaded on first expand
- Column rows show name (monospace), type, and required indicator (red dot = required, grey = optional)
- Live search to filter tables by name

#### Query Log
- Tabbed view: Write Queries / Read Queries
- Filters: status, query type, keyword, date range, submitter (admin only)
- Detail modal: full SQL, risk flags, review details, execution details, snapshot data, rollback info

#### Schema & Infrastructure
- Schema introspection cache for table list and column metadata (default TTL: 300 s, `DB_GOVERNOR_SCHEMA_CACHE_TTL`)
- `required` field returned by all three drivers (`listColumns`): MySQL, PostgreSQL, SQLite
- Multi-connection support — map multiple Laravel DB connections to URL slugs
- Configurable hidden tables list (framework-internal tables excluded by default)
- Separate governance connection support (`DB_GOVERNOR_STORAGE_CONNECTION`)
- Read query audit logging (`DB_GOVERNOR_LOG_READS`)

#### Developer Experience
- Zero-dependency web UI (Alpine.js + Tailwind via CDN, no build step)
- `vendor:publish` tags: `db-governor-config`, `db-governor-views`
- Composer scripts: `test`, `test:coverage`, `lint`, `lint:fix`
- Full Pest feature + unit test suite
- Laravel 10 / 11 / 12 / 13 support; PHP `^8.1`

### Fixed
- Populate `query_table` on every log entry (read, write submit, filter browse)
- Auto-generate human-readable name from SQL for read queries
- Bind values into `sql_raw` instead of leaving `?` placeholders
- Correctly surface SQL execution errors instead of always flashing "success"
- Auto-select pre-filled columns in INSERT dropdown via `:selected` binding

---

[Unreleased]: https://github.com/mamun724682/laravel-db-governor/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/mamun724682/laravel-db-governor/releases/tag/v1.0.0

