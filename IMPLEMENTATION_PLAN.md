# Implementation Plan — DB Governor Code Review

> **Context:** Internal dev-team tool, not publicly exposed.  
> Priority is correctness, data integrity, and long-term maintainability over hardening against external attackers.

---

## Phase 1 — Critical Bugs & Data Integrity

These are broken-by-design issues that can silently produce wrong results or corrupt audit data right now.

---

### 1.1 SQL Injection in `preCheckWhereRows()` and `captureBeforeState()`

**Files:** `src/Services/ApprovalService.php:146`, `src/Services/RollbackService.php:54`

**Problem:** Both methods extract the WHERE clause from user-submitted SQL via regex, then
interpolate it verbatim into a new query:

```php
// ApprovalService.php
$conn->selectOne("SELECT COUNT(*) as cnt FROM {$q}{$table}{$q} WHERE {$where}");

// RollbackService.php
$conn->select("SELECT * FROM {$q}{$table}{$q} WHERE {$where}");
```

An authenticated employee can submit SQL whose WHERE clause contains a subquery or
UNION that runs against any non-hidden table, bypassing the approval workflow entirely.

**Fix — `preCheckWhereRows()`:**  
Use `EXPLAIN` or count via the ORM rather than re-executing a hand-built query. The safest
approach is to attempt `$inspector->estimateAffectedRows($sql, $conn)` (already available)
and treat a zero estimate as the rejection signal. The raw WHERE string must never be
re-injected into a new query.

**Fix — `captureBeforeState()`:**  
Capture the snapshot by executing the *original approved* SQL inside a transaction that is
immediately rolled back (a `SELECT … FOR UPDATE` inside a `savepoint`/`rollback`), or skip
the WHERE re-execution entirely and use `DB::pretend()` to derive the affected row set.
Alternatively, restrict snapshot capture to queries whose WHERE clause contains only
simple `col = ?` comparisons detected via a strict parser, and skip snapshot for anything
more complex.

---

### 1.2 `rollback_strategy` Config Is Never Read

**Files:** `src/Services/QueryExecutor.php:48`, `src/Services/RollbackService.php:22–68`

**Problem:** `config('db-governor.rollback_strategy')` is documented as supporting
`'row_snapshot'`, `'generated_sql'`, and `'none'`, but `QueryExecutor::executeWrite()`
always calls `captureBeforeState()` regardless. The config option does nothing.

**Fix:**
```
just remove the config option 
```

---

### 1.3 Status Transitions Are Unenforced

**File:** `src/Services/ApprovalService.php:65–106`

**Problem:** `approve()`, `reject()`, and `rollback()` perform no status guard.
A race condition (or a developer calling the wrong action) can produce nonsense states:
- approve an already-executed query
- rollback a pending (never-executed) query
- reject a rolled-back query

**Fix:** Add guard clauses at the start of each method:

| Method | Allowed current statuses |
|--------|--------------------------|
| `approve()` | `pending` |
| `reject()` | `pending` |
| `execute()` | `approved` (already has this check in `QueryExecutor`) |
| `rollback()` | `executed` |

Throw `\LogicException` or a new `InvalidTransitionException` when the guard fails.
These throw up to `QueryController::action()`, which catches `\Throwable` and converts to
a flash error — no UI changes needed.

---

### 1.4 `submitted_ip` Never Populated

**File:** `src/Services/ApprovalService.php:38–50`, `src/Models/GovernedQuery.php:21`

**Problem:** `submitted_ip` is in the migration, `$fillable`, and visible in the UI, but
`submit()` never sets it. The column is always NULL. Every audit record is missing the
submitter IP.

**Fix:** Add to the attributes array in `ApprovalService::submit()`:
```php
'submitted_ip' => request()->ip(),
```

---

## Phase 2 — Performance

---

### 2.1 Dashboard: 6 COUNT Queries → 1 GROUP BY

**File:** `src/Http/Controllers/DashboardController.php:24–31`

**Problem:** Six separate `SELECT COUNT(*)` queries fire on every dashboard load.

**Fix:**
```php
$counts = GovernedQuery::where('connection', $connection)
    ->when(! $this->guard->isAdmin(), fn ($q) => $q->where('submitted_by', $this->guard->email()))
    ->selectRaw('status, COUNT(*) as total')
    ->groupBy('status')
    ->pluck('total', 'status');

$stats = [
    'pending'     => $counts[QueryStatus::Pending->value]     ?? 0,
    'approved'    => $counts[QueryStatus::Approved->value]    ?? 0,
    'executed'    => $counts[QueryStatus::Executed->value]    ?? 0,
    'rejected'    => $counts[QueryStatus::Rejected->value]    ?? 0,
    'rolled_back' => $counts[QueryStatus::RolledBack->value]  ?? 0,
    'blocked'     => $counts[QueryStatus::Blocked->value]     ?? 0,
];
```

---

### 2.2 `detectCascadeTables()` N+1 Table Loop

**File:** `src/Services/ConnectionManager.php:96–128`

**Problem:** Loops every table in the schema and calls `getForeignKeys()` on each,
resulting in N introspection queries for a schema with N tables.

**Fix:** Add a `detectCascadeTables(string $targetTable, Connection $conn): array` method
to the `DbInspector` interface with driver-specific single-query implementations:

- **MySQL:** `INFORMATION_SCHEMA.KEY_COLUMN_USAGE` joined with `REFERENTIAL_CONSTRAINTS`
  filtered by `REFERENCED_TABLE_NAME = ? AND DELETE_RULE = 'CASCADE'`.
- **PostgreSQL:** `pg_constraint` joined with `pg_class` for `confdeltype = 'c'` and
  `confrelid = target_table_oid`.
- **SQLite:** No FK metadata in system tables at schema level — fall back to the current
  `PRAGMA foreign_key_list` loop (acceptable since SQLite is never used on large schemas).

`ConnectionManager::detectCascadeTables()` then delegates to the inspector.

---

### 2.3 `ConnectionManager::inspector()` Double-Resolves the Connection

**File:** `src/Services/ConnectionManager.php:55–65`

**Problem:** `inspector(key)` calls `driver(key)` which calls `resolve(key)`. Every caller
also calls `resolve(key)` separately — two `DB::connection()` lookups per operation.

**Fix:** Refactor `inspector()` to accept an optional pre-resolved `Connection`:
```php
public function inspector(string $key, ?Connection $conn = null): DbInspector
{
    $driver = ($conn ?? $this->resolve($key))->getDriverName();
    return match ($driver) { ... };
}
```
Callers that already hold `$conn` pass it in. Eliminates the redundant resolve.

---

### 2.4 `AccessGuard` Re-reads Config 3× Per Request

**File:** `src/Services/AccessGuard.php:115–132`

**Problem:** `validateToken()` calls `adminEmails()` twice and `employeeEmails()` once.
Each call does `array_map('strtolower', config(...))`. Runs on every authenticated request.

**Fix:** Memoize in the constructor or on first access:
```php
private ?array $cachedAdmins    = null;
private ?array $cachedEmployees = null;

private function adminEmails(): array
{
    return $this->cachedAdmins ??= array_map('strtolower', config('db-governor.allowed.admins', []));
}
```

---

## Phase 3 — Duplicate Code Extraction

These are maintenance hazards that grow in scope every time a feature is added.

---

### 3.1 Extract `extractWhere()` into `QueryClassifier`

**Duplicated in:** `ApprovalService.php:137`, `RollbackService.php:48`

Add `QueryClassifier::extractWhere(string $sql): ?string` that encapsulates the regex.
Both callers use the result, so this is a straight refactor.

---

### 3.2 Remove Inline Driver-Quoting from Services

**Duplicated in:** `ApprovalService.php:145`, `RollbackService.php:53`, `RollbackService.php:86`

All three lines do `$q = $conn->getDriverName() === 'mysql' ? '`' : '"'` then use `$q`
manually. Each place should instead call `$inspector->quoteIdentifier($table)` and
`$inspector->quoteIdentifier($col)`, which is what the driver layer is for.
Requires passing the inspector into the two services (already injected in `RollbackService`;
needs to be added to `ApprovalService` for the pre-check).

---

### 3.3 Extract Read Query Audit Logging

**Duplicated in:** `SqlController.php:58–72`, `TableController.php:77–89`

Add `QueryExecutor::logRead(string $sql, string $connection, string $table, QueryResult $result): void`. Both controllers call it instead of
duplicating the `GovernedQuery::create()` block.

---

### 3.4 Move Column Caching into `ConnectionManager::listColumns()`

**Duplicated in:** `SchemaController.php:43–47`, `TableController.php:37–41`

Add:
```php
public function listColumns(string $key, string $table): array
{
    $ttl = config('db-governor.schema_cache_ttl', 300);
    return Cache::remember("db-governor.columns.{$key}.{$table}", $ttl, function () use ($key, $table) {
        return $this->inspector($key)->listColumns($table, $this->resolve($key));
    });
}
```
Both controllers call `$this->connectionManager->listColumns($connection, $table)`.

---

### 3.5 Consolidate SQL Name Generation

**Duplicated in:** `SqlController::nameFromSql()`, `TableController::nameFromFilterSql()`

Add `QueryClassifier::generateAuditName(string $sql, ?string $whereHint = null): string`.
Both controllers delegate to it.

---

## Phase 4 — Maintainability & Correctness

---

### 4.1 Add Route Constraint on `{action}`

**File:** `routes/web.php:51`

```php
Route::post('/queries/{query}/{action}', [QueryController::class, 'action'])
    ->where('action', 'approve|reject|execute|rollback')
    ->name('db-governor.queries.action');
```
Laravel returns a 404 automatically for any other string, before the controller is reached.

---

### 4.2 Remove `id` from `GovernedQuery::$fillable`

**File:** `src/Models/GovernedQuery.php:17`

`HasUuids` manages UUID generation. `'id'` should not be mass-assignable. Remove it from
the `$fillable` array.

---

### 4.3 Add `snapshot_data` to Model Casts

**File:** `src/Models/GovernedQuery.php:51–56`

```php
'snapshot_data' => 'array',
```

Remove the manual `json_encode` in `QueryExecutor::executeWrite()` and `json_decode` in
`RollbackService::rollback()`. The model handles serialization consistently.

---

### 4.4 Add Rate Limiting to Login

**File:** `src/Http/Controllers/AuthController.php`

Even for an internal tool, rate-limiting the login endpoint is good practice (prevents
accidental loops and any unauthorized network access from hammering the endpoint).

```php
use Illuminate\Support\Facades\RateLimiter;

$key = 'dbg-login:' . $request->ip();
if (RateLimiter::tooManyAttempts($key, 10)) {
    return back()->with('error', 'Too many login attempts. Try again later.');
}
RateLimiter::hit($key, 60);
```

---

## Phase 5 — Test Coverage

Fill the gaps identified in the review.

| Test | File to add/extend | What to cover |
|------|--------------------|---------------|
| Status transition guards | `tests/Feature/ApprovalServiceTest.php` | approve/reject/rollback on wrong-state queries throw |
| Rollback UPDATE restoration | `tests/Feature/RollbackServiceTest.php` | UPDATE → execute → rollback restores original values |
| `submitted_ip` populated | `tests/Feature/ApprovalServiceTest.php` | `submit()` sets `submitted_ip` from request |
| Blocked pattern + comment bypass | `tests/Feature/RiskAnalyzerTest.php` | `/* */ DROP TABLE` is blocked |
| `detectCascadeTables` fast path (MySQL) | `tests/Unit/ConnectionManagerTest.php` | Single query emitted, not N queries |
| `dashboard` single query | `tests/Feature/DashboardControllerTest.php` | Assert 1 DB query fires, not 6 |
| Filter-browse deduplication | `tests/Feature/TableFilterLoggingTest.php` | Page 2 does not create a second audit row |
| `QueryController::action` all branches | `tests/Feature/ControllersTest.php` | approve/reject/execute/rollback routes return correct responses |

---

## Execution Order

```
Phase 1  →  Phase 2.1, 2.2  →  Phase 3  →  Phase 2.3–2.5  →  Phase 4  →  Phase 5
```

Phases 1 and 2.1/2.2 have the highest payoff-per-effort ratio.  
Phase 3 (duplication cleanup) is easiest to do incrementally — tackle one item per PR.  
Phase 5 tests should be written **alongside** each fix, not after.

---

## Files Changed Per Phase (Quick Reference)

| Phase | Files |
|-------|-------|
| 1.1 | `ApprovalService.php`, `RollbackService.php` |
| 1.2 | `QueryExecutor.php`, `RollbackService.php` |
| 1.3 | `ApprovalService.php` |
| 1.4 | `ApprovalService.php` |
| 2.1 | `DashboardController.php` |
| 2.2 | `DbInspector.php`, `MySqlInspector.php`, `PgsqlInspector.php`, `SqliteInspector.php`, `ConnectionManager.php` |
| 2.3 | `ConnectionManager.php`, `DryRunEngine.php`, `RollbackService.php` |
| 2.4 | `AccessGuard.php` |
| 2.5 | `TableController.php` |
| 3.1 | `QueryClassifier.php`, `ApprovalService.php`, `RollbackService.php` |
| 3.2 | `ApprovalService.php`, `RollbackService.php` |
| 3.3 | `QueryExecutor.php`, `SqlController.php`, `TableController.php` |
| 3.4 | `ConnectionManager.php`, `SchemaController.php`, `TableController.php` |
| 3.5 | `QueryClassifier.php`, `SqlController.php`, `TableController.php` |
| 4.1 | `routes/web.php` |
| 4.2 | `GovernedQuery.php` |
| 4.3 | `GovernedQuery.php`, `QueryExecutor.php`, `RollbackService.php` |
| 4.4 | `AuthController.php` |
| 5   | `tests/Feature/*`, `tests/Unit/*` |
