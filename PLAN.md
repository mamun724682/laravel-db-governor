# Laravel DB Governor — Comprehensive Improvement Plan

> **Package:** `mamun724682/laravel-db-governor`
> **Analysed revision:** current HEAD (tested on Laravel 10–13)
> **Date:** April 2026

---

## Executive Summary — Most Critical Issues

- 🔴 **Token-in-URL security flaw** — Session tokens are embedded in every URL (`/{token}/...`), meaning they appear in server access logs, browser history, and HTTP `Referer` headers. This is a significant credential-leakage risk for a security-focused package.
- 🔴 **CDN-loaded Tailwind & Alpine with no version pinning** — `tailwindcss` and `alpinejs@3.x.x` are pulled from CDN at runtime. Any CDN outage, supply-chain attack, or accidental API break will take the governance UI offline in production.
- 🔴 **No login rate limiting** — The `/login` POST endpoint has no brute-force protection. An attacker can enumerate email addresses indefinitely.
- 🔴 **`@json($query->toArray())` exposes sensitive columns to the browser** — The query-detail modal serialises the entire `GovernedQuery` model (including `snapshot_data`, potentially megabytes of row data) directly into page HTML.
- 🔴 **`QueryExecutor::executeWrite()` is not atomic** — The before-state snapshot is captured outside any transaction. A crash between snapshot capture and the model update leaves the audit record in a corrupt state.
- 🟡 **No events/listeners** — No event is dispatched on query submission, approval, execution, or rollback, making the package impossible to extend without forking.
- 🟡 **`DashboardController` fires 6 separate COUNT queries** — A single `GROUP BY status` query would replace all six.
- 🟡 **All exceptions extend bare `\RuntimeException`** — None implement `render()`, so uncaught exceptions result in a plain 500 page rather than meaningful HTTP responses.
- 🟡 **No Artisan commands** — No `db-governor:install`, `db-governor:purge`, or `db-governor:check` command.
- 🟢 **No CHANGELOG.md** — Essential for a publishable Packagist package.

---

## 1. `composer.json`

### 1.1 Version constraints are over-specified
🟡 **Important** | Effort: S

`illuminate/*` dependencies include `^13` which did not exist at time of writing. Adding speculative future major versions risks resolving against incompatible future releases. Extend constraints only when a new major is released and tested. Same applies to `orchestra/testbench` and `pestphp/pest`.

### 1.2 `minimum-stability: dev`
🟡 **Important** | Effort: S

Setting `minimum-stability: dev` in a library affects every project that installs it unless they override it. Remove entirely or set to `stable`.

### 1.3 No `aliases` key in `extra.laravel`
🟢 **Nice-to-have** | Effort: S

If a `DbGovernor` facade is added (see section 15), register it under `extra.laravel.aliases` for zero-config auto-discovery.

### 1.4 No static analysis tooling in `require-dev`
🟢 **Nice-to-have** | Effort: S

No `phpstan/phpstan` or `larastan/larastan` in dev dependencies. Adding type checking as a CI gate would catch type inconsistencies before they reach users.

### 1.5 `test:coverage` script has no coverage driver configured
🟢 **Nice-to-have** | Effort: S

`vendor/bin/pest --coverage` fails silently in environments without pcov/xdebug. Add `--coverage-html` or document the driver requirement.

---

## 2. `DbGovernorServiceProvider`

### 2.1 Views are not publishable
🟡 **Important** | Effort: S

No `'db-governor-views'` publish tag exists. Teams that need to customise the UI have no supported path. Add a publish group pointing to `resources/views`.

### 2.2 Migrations are not publishable
🟡 **Important** | Effort: S

`loadMigrationsFrom()` auto-runs migrations but users who need to customise the table structure have no way to publish and modify them. Add a `'db-governor-migrations'` publish tag.

### 2.3 `View::composer` swallows all `\Throwable`
🟡 **Important** | Effort: S

The broad `catch (\Throwable)` inside the view composer silently eats all exceptions from `AccessGuard`, hiding genuine programming errors. Narrow the catch to only the specific "not authenticated" case.

### 2.4 `RiskAnalyzer` binding reads config in `register()`
🟡 **Important** | Effort: S

The binding closure calls `config()` at registration time, which is fragile in certain Octane/boot sequences. The closure already runs lazily so this is low risk in practice, but a `DeferrableProvider` would be cleaner.

### 2.5 No `provides()` method for deferred resolution
🟢 **Nice-to-have** | Effort: S

`ConnectionManager` and `AccessGuard` are singletons but not needed on every request. Implementing `DeferrableProvider` would avoid unnecessary resolution on unrelated requests.

---

## 3. Routing

### 3.1 Token embedded in URL is a security risk
🔴 **Critical** | Effort: M

Every protected route contains the session token as a URL path segment (`/{token}/...`). This token ends up in server access logs, browser history, and `Referer` headers sent to CDNs. Sessions should be stored in a cookie or Laravel session; the token could remain as an additional CSRF-style validation but should not be the sole credential embedded in every URL.

### 3.2 No rate limiting on the login route
🔴 **Critical** | Effort: S

The `POST /login` route has no `throttle` middleware. Apply Laravel's built-in `throttle:6,1` (or a named rate limiter) to the login route to prevent brute-force email enumeration.

### 3.3 Free-text `{action}` route is error-prone
🟡 **Important** | Effort: S

`POST /queries/{query}/{action}` routes any string through `QueryController::action()`. Separate named routes per action (`/queries/{query}/approve`, `/queries/{query}/reject`, etc.) are more RESTful, easier to authorise individually, and eliminate the wildcard surface area.

### 3.4 No route model binding for `GovernedQuery`
🟢 **Nice-to-have** | Effort: S

The `{query}` parameter is a raw UUID resolved manually in the service layer. Registering a route model binding in the service provider would make resolution automatic and consistent with Laravel conventions.

---

## 4. `GovernedQuery` Model

### 4.1 `created_at` and `updated_at` are in `$fillable`
🔴 **Critical** | Effort: S

Timestamp columns should never be mass-assignable — this allows callers to forge audit timestamps, directly undermining the integrity of the audit log. Remove both from `$fillable`.

### 4.2 Enum casts are missing
🟡 **Important** | Effort: S

`status`, `query_type`, `risk_level`, and `snapshot_strategy` are stored as strings but never cast to their corresponding Enum types. Adding backed enum casts eliminates the proliferation of `.value` calls throughout the codebase and provides type safety at the model boundary.

### 4.3 `snapshot_data` should be hidden from serialisation
🟡 **Important** | Effort: S

`snapshot_data` can contain hundreds of serialised database rows. Add it to `$hidden` to prevent it being inadvertently included in `toArray()` calls (which currently power the query-detail modal — see issue 13.3).

### 4.4 No query scopes
🟢 **Nice-to-have** | Effort: S

Filters like `->where('status', ...)`, `->where('connection', ...)`, and `->where('submitted_by', ...)` are repeated across three controllers. Extract as named Eloquent scopes (`scopePending()`, `scopeByConnection()`, etc.).

### 4.5 `$incrementing = false` and `$keyType = 'string'` are redundant
🟢 **Nice-to-have** | Effort: S

`HasUuids` already sets both properties. The explicit declarations are harmless but add noise.

### 4.6 `getConnectionName()` calls `config()` on every instantiation
🟢 **Nice-to-have** | Effort: S

Resolved once in the service provider or cached as a class property would be more explicit and avoid repeated config lookups.

---

## 5. Migration

### 5.1 No composite indexes on common query patterns
🟡 **Important** | Effort: S

The dashboard and query list always filter by `connection` AND `status`. Add composite indexes on `(connection, status)` and `(submitted_by, status)` to support these read patterns.

### 5.2 Migration reads config in `up()` and `down()`
🟡 **Important** | Effort: S

`config('db-governor.table_name', 'dbg_queries')` is called inside the migration. During `migrate --pretend` or when config is not loaded, this can produce unexpected results. The table name should be a class constant as the fallback.

### 5.3 `governance_connection` migration mismatch
🟡 **Important** | Effort: S

`GovernedQuery::getConnectionName()` routes model queries to `governance_connection`, but `Schema::create()` uses the default connection. If `governance_connection` points to a different server, the table is created on the wrong connection. The migration should call `Schema::connection(...)` consistently.

### 5.4 `submitted_ip` should use `ipAddress()` column type
🟢 **Nice-to-have** | Effort: S

`$table->string('submitted_ip')` allocates 255 bytes. `$table->ipAddress()` is more appropriate (45 chars, accommodates IPv6) and communicates intent.

### 5.5 `risk_note` is `string` (255 char limit) but peer columns are `text`
🟢 **Nice-to-have** | Effort: S

`review_note`, `execution_error`, and `rollback_error` are all `text`. A risk note may legitimately exceed 255 characters; change to `text`.

### 5.6 `snapshot_data` column does not use `json` type
🟢 **Nice-to-have** | Effort: S

`snapshot_data` stores JSON but is typed as `longText`. Using `$table->json()` on MySQL/Postgres enables native JSON operators and communicates intent.

---

## 6. Services

### 6.1 `QueryExecutor::executeWrite()` is not atomic
🔴 **Critical** | Effort: M

Snapshot capture, `affectingStatement()`, and the `GovernedQuery` model update are three separate operations with no wrapping transaction. A failure between snapshot and model update leaves the audit record in a corrupt state and prevents rollback. Wrap in a DB transaction, using a separate connection for governance operations if configured.

### 6.2 `RollbackService::rollback()` checks `rolled_back_at` but not `status`
🟡 **Important** | Effort: S

The rollback eligibility check only validates `rolled_back_at === null`. A pending or rejected query with no `rolled_back_at` would be incorrectly eligible. Add an explicit `status === QueryStatus::Executed->value` guard.

### 6.3 `ApprovalService` has six constructor dependencies
🟡 **Important** | Effort: M

Six injected dependencies suggest this service handles too many concerns. Split into a smaller `SubmissionService` (submit + pre-check) and `ReviewService` (approve/reject/execute/rollback).

### 6.4 `preCheckWhereRows()` has fragile regex-based SQL parsing
🟡 **Important** | Effort: M

Regex extraction of table names and WHERE clauses from raw SQL will silently fail or produce incorrect results for CTEs, subqueries, aliases, and multi-table JOINs. The happy-path `return null` masks failures. Document known limitations or restrict to simple single-table patterns only.

### 6.5 `AccessGuard::assertAdmin()` calls `abort()` inside a service
🟡 **Important** | Effort: S

`abort(403)` inside a service couples it to the HTTP layer and makes it untestable without a full HTTP kernel. Throw a domain exception instead and let the controller/exception handler convert it.

### 6.6 No interfaces for services
🟡 **Important** | Effort: M

None of the services have interfaces, making them impossible to swap via the container and requiring class mocking in tests. Define `Contracts\AccessGuardInterface`, etc., bind them in the service provider, and update type hints.

### 6.7 `ConnectionManager::inspector()` uses `new` directly
🟢 **Nice-to-have** | Effort: S

Inspectors are instantiated with `new` in a `match` expression. Resolving them via the container would allow them to be decorated, logged, or swapped.

---

## 7. DTOs

### 7.1 Untyped `array` properties throughout DTOs
🟡 **Important** | Effort: S

`RiskReport::$flags`, `QueryResult::$rows`, and `SnapshotData::$rows` are typed as bare `array`. Add PHPDoc generics (`@var array<int, string>`, `@var array<int, array<string, mixed>>`) for better static analysis and IDE support.

### 7.2 `PendingQuery` lacks input validation
🟡 **Important** | Effort: S

`PendingQuery::$sql` could be whitespace-only or extremely long. Consider a named constructor or factory method that validates inputs, or delegate to a `FormRequest` before construction.

### 7.3 `SnapshotData::$primaryKey` is non-nullable but may be meaningless
🟢 **Nice-to-have** | Effort: S

Inspectors fall back to the string `'id'` when no primary key is detected. If a table genuinely has no primary key, rollback would silently use a non-existent column. Type as `?string` and handle the null case explicitly in `RollbackService`.

---

## 8. Enums

### 8.1 No `label()` helper on enums
🟡 **Important** | Effort: S

Every view that displays a status uses `strtoupper(str_replace('_', ' ', $query->status))`. This is duplicated across `queries.blade.php`, the dashboard, and the JavaScript modal. A `label()` method on `QueryStatus`, `QueryType`, and `RiskLevel` would centralise this logic.

### 8.2 No `badgeClass()` or `color()` helpers
🟡 **Important** | Effort: S

The `$statusColors` and `$riskColors` PHP arrays in `queries.blade.php` are re-implemented as JavaScript ternaries inside the Alpine component. A `badgeClasses()` method on each enum would be the single source of truth, usable in both Blade and serialised to JS via `@json`.

### 8.3 `QueryType` is missing `isWrite()` and `isDdl()` helpers
🟢 **Nice-to-have** | Effort: S

`QueryType` has `isRead()` but the companion methods are missing. Add them for consistency.

---

## 9. Exceptions

### 9.1 No exceptions implement `render()`
🔴 **Critical** | Effort: S

`QueryBlockedException`, `QueryNotApprovedException`, `InvalidConnectionException`, and `RollbackFailedException` all extend bare `\RuntimeException`. When unhandled they produce a 500 page. Implement `render(Request $request): Response` on each to return appropriate HTTP responses (403, 422, 404, 500) with clear messages.

### 9.2 `RollbackFailedException` is dead code
🟡 **Important** | Effort: S

`RollbackService::rollback()` returns a `RollbackResult` DTO on failure rather than throwing this exception. The class exists but is never thrown. Wire it into the failure path or remove it.

### 9.3 Exception HTTP codes should be set explicitly
🟢 **Nice-to-have** | Effort: S

`InvalidConnectionException` → 404, `QueryNotApprovedException` → 409/422, `QueryBlockedException` → 403. Set the appropriate `$code` in each constructor for API usability.

---

## 10. Middleware

### 10.1 `AccessGuard` singleton is mutated per-request — Octane incompatibility
🔴 **Critical** | Effort: M

`DbGovernanceAccess::handle()` calls `$this->guard->setPayload($payload)` on the singleton `AccessGuard`. Under Octane (Swoole/RoadRunner), singletons persist across requests, causing payload bleed between users. Use a scoped (per-request) binding or store the payload on the request object instead.

### 10.2 On invalid connection, `abort(404)` is used instead of redirect
🟡 **Important** | Effort: S

For a browser UI, a redirect to the dashboard with a flash error message is more appropriate than a hard 404.

### 10.3 No sliding token expiry
🟢 **Nice-to-have** | Effort: S

A user active near the expiry time is abruptly logged out without warning. Extending the cache TTL on each validated request would provide a better experience.

---

## 11. Controllers

### 11.1 No `FormRequest` classes
🟡 **Important** | Effort: M

Validation is inline in `QueryController::store()` and `SqlController::execute()`. Extract into `StoreQueryRequest` and `ExecuteSqlRequest` for reusability, isolation, and consistency with Laravel conventions.

### 11.2 `DashboardController` runs 6 separate COUNT queries
🟡 **Important** | Effort: S

Replace all 6 `COUNT(*)` queries with a single `SELECT status, COUNT(*) … GROUP BY status` query.

### 11.3 `TableController` is too large with mixed concerns
🟡 **Important** | Effort: M

`TableController::show()` handles routing, SQL construction, pagination, and audit logging. The `buildWhere()` method (61 lines) and audit-logging code belong in a dedicated `FilterQueryBuilder` service.

### 11.4 Controllers do not extend `\Illuminate\Routing\Controller`
🟢 **Nice-to-have** | Effort: S

Extending Laravel's base `Controller` provides access to `$this->middleware()`, `$this->validate()`, and `authorize()`.

### 11.5 `nameFromSql()` logic is duplicated across controllers
🟢 **Nice-to-have** | Effort: S

`SqlController::nameFromSql()` and `TableController::nameFromFilterSql()` both generate audit log labels. Extract to a shared helper or `AuditNameResolver` service.

---

## 12. Drivers

### 12.1 `PgsqlInspector` hardcodes `table_schema = 'public'`
🟡 **Important** | Effort: S

Any Postgres database using a non-default schema (common in multi-tenant, Supabase setups) will return no tables. The schema should be configurable, defaulting to `'public'`.

### 12.2 `PgsqlInspector::estimateAffectedRows()` parses text EXPLAIN output
🟡 **Important** | Effort: S

Use `EXPLAIN (FORMAT JSON)` and access `Plan.Plan Rows` instead of regex-parsing the text output.

### 12.3 No SQL Server (sqlsrv) driver
🟢 **Nice-to-have** | Effort: M

SQL Server is a supported Laravel database driver but throws `InvalidConnectionException` in this package. Add a `SqlSrvInspector` or document the limitation.

### 12.4 `SqliteInspector::estimateAffectedRows()` returns `null` for SELECT
🟢 **Nice-to-have** | Effort: S

Add a `EXPLAIN QUERY PLAN` scan to estimate rows for SELECT for consistency with the other drivers.

### 12.5 Inspector instances are not cached
🟢 **Nice-to-have** | Effort: S

`ConnectionManager::inspector()` creates a new instance on every call. Memoize inside `ConnectionManager` to avoid redundant instantiation.

---

## 13. Views

### 13.1 Tailwind CSS and Alpine.js loaded from CDN without version pinning
🔴 **Critical** | Effort: M

The Tailwind CDN is explicitly marked by Tailwind Labs as not suitable for production. A supply-chain attack or breaking version bump on either CDN could break the entire UI. Bundle both assets as compiled package assets (pre-built CSS/JS files checked into the package) and make them optionally publishable.

### 13.2 `login.blade.php` and `connections.blade.php` do not extend the base layout
🟡 **Important** | Effort: S

Both views define a complete standalone HTML document, duplicating CDN script includes. Any change to the CDN URL must be made in three places. Both pages should extend the base layout or a minimal shared head partial.

### 13.3 `@json($query->toArray())` exposes all model data to the browser
🔴 **Critical** | Effort: S

The query-detail modal serialises the entire model row into page HTML, including `snapshot_data` (potentially megabytes of production row data) and `submitted_ip`. Use `$query->only([...])` with an explicit allowlist of safe columns, or a dedicated API resource transformer.

### 13.4 No ARIA roles or attributes on modals
🟡 **Important** | Effort: S

Modal dialogs are missing `role="dialog"`, `aria-modal="true"`, `aria-labelledby`, and focus traps. Background overlays are missing `aria-hidden="true"`. These fail WCAG 2.1 AA criteria 4.1.2 and 2.1.2.

### 13.5 No `role="alert"` on flash message banners
🟡 **Important** | Effort: S

Success/error flash `<div>` elements lack `role="alert"` and `aria-live="polite"`, so screen reader users are not notified after page load.

### 13.6 Data tables lack accessibility attributes
🟡 **Important** | Effort: S

Tables do not use `<caption>` and `<th>` elements have no `scope="col"`, making them difficult to navigate with screen readers.

### 13.7 `queries.blade.php` is ~990 lines
🟡 **Important** | Effort: M

Mix of query list, filter form, detail modal, SQL console, and a large Alpine component. Decompose into included partials: `query-table`, `query-detail-modal`, `sql-console`.

### 13.8 No `<meta name="robots" content="noindex, nofollow">`
🟡 **Important** | Effort: S

The governance UI at `/db-governor/...` is publicly crawlable. Add `noindex, nofollow` to all layouts.

### 13.9 Status and risk colour maps are duplicated in PHP and JavaScript
🟢 **Nice-to-have** | Effort: S

`$statusColors` and `$riskColors` PHP arrays are re-implemented as Alpine `:class` ternaries. Enum helpers (see section 8.2) would eliminate the duplication.

### 13.10 No `<noscript>` fallback
🟢 **Nice-to-have** | Effort: S

The entire UI depends on Alpine.js. Add a `<noscript>` banner informing users that JavaScript is required.

---

## 14. Tests

### 14.1 No `<source>` element in `phpunit.xml`
🔴 **Critical** | Effort: S

Without a `<source>` pointing to `src/`, coverage reports do not instrument the package files. Untested lines are silently excluded from metrics.

### 14.2 All feature tests use SQLite only; driver-specific behaviour is untested
🟡 **Important** | Effort: L

`MySqlInspector` (`SHOW KEYS`, `SHOW COLUMNS`, `EXPLAIN` parsing) and `PgsqlInspector` (schema-qualified queries, JSON EXPLAIN format) are never exercised. Add a CI matrix against real MySQL/Postgres instances, or use tagged test groups.

### 14.3 `MobileResponsivenessTest` and `ModalBehaviorTest` give false coverage signals
🟡 **Important** | Effort: S

These names imply front-end testing that PHPUnit cannot perform. They likely test only HTTP status codes. Rename to reflect what they actually test, or move to a Dusk/Playwright suite.

### 14.4 No tests for `RollbackService::captureBeforeState()` edge cases
🟡 **Important** | Effort: S

Untested branches: composite PKs, no-WHERE queries, snapshots exceeding `snapshot_max_rows`, inspector exceptions. These are the exact cases most likely to fail silently in production.

### 14.5 No tests for `TableController::buildWhere()` filter injection
🟡 **Important** | Effort: S

The method constructs SQL WHERE clauses from user input. No dedicated tests for injection edge cases (column names with special chars, empty groups, `IN` operator).

### 14.6 No mutation testing
🟢 **Nice-to-have** | Effort: M

Add `infection/infection` to `require-dev` with a `mutation-testing` composer script to identify weak assertions.

---

## 15. General

### 15.1 No events or listeners
🟡 **Important** | Effort: M

No events are dispatched at any lifecycle point. Teams cannot hook into submission, approval, execution, or rollback without forking. Add `QuerySubmitted`, `QueryApproved`, `QueryRejected`, `QueryExecuted`, `QueryRolledBack` events dispatched from the appropriate services.

### 15.2 No Artisan commands
🟡 **Important** | Effort: M

Add at minimum:
- `db-governor:install` — publishes config and migrations, prints quick-start instructions
- `db-governor:purge {--days=90}` — archives/deletes audit records older than N days
- `db-governor:check` — validates config is well-formed and all connections are reachable

### 15.3 No `CHANGELOG.md`
🟡 **Important** | Effort: S

Create a `CHANGELOG.md` following the Keep a Changelog format and commit to maintaining it alongside each tagged release.

### 15.4 No config validation on boot
🟡 **Important** | Effort: S

Misconfigured `allowed.admins` (e.g., non-lowercase emails, empty arrays) or an invalid `rollback_strategy` value causes confusing runtime failures. A `ConfigValidator` invoked in `boot()` would surface misconfiguration at startup.

### 15.5 No facade
🟢 **Nice-to-have** | Effort: S

A `DbGovernor` facade wrapping `ApprovalService` (or a dedicated entry-point class) would allow teams to submit queries programmatically from seeders, CI scripts, or application code without manually resolving from the container.

### 15.6 `README.md` lacks a copy-paste quick-start section
🟢 **Nice-to-have** | Effort: S

Add a numbered quick-start at the top: `composer require`, `vendor:publish --tag=db-governor-config`, `php artisan migrate`, `.env` variable setup. The compatible Laravel version range in any badge should match only tested versions.

---

## Priority Order (Recommended Implementation Sequence)

| # | Issue | Severity | Effort |
|---|-------|----------|--------|
| 1 | Rate limiting on `/login` | 🔴 | S |
| 2 | `created_at`/`updated_at` removed from `$fillable` | 🔴 | S |
| 3 | `snapshot_data` added to `$hidden` + modal uses allowlist | 🔴 | S |
| 4 | Exceptions implement `render()` | 🔴 | S |
| 5 | `phpunit.xml` add `<source>` for coverage | 🔴 | S |
| 6 | `QueryExecutor::executeWrite()` wrapped in transaction | 🔴 | M |
| 7 | Octane-safe `AccessGuard` payload (scoped binding) | 🔴 | M |
| 8 | Bundle Tailwind + Alpine as package assets | 🔴 | M |
| 9 | Token-in-URL → session-based auth | 🔴 | M |
| 10 | Enum casts on `GovernedQuery` | 🟡 | S |
| 11 | Composite DB indexes on migration | 🟡 | S |
| 12 | `db-governor:install` / `db-governor:purge` commands | 🟡 | M |
| 13 | Events: `QuerySubmitted`, `QueryExecuted`, etc. | 🟡 | M |
| 14 | Service interfaces + FormRequest classes | 🟡 | M |
| 15 | `CHANGELOG.md` + README quick-start | 🟡 | S |

