<?php

namespace Mamun724682\DbGovernor\Services;

use Mamun724682\DbGovernor\DTOs\PendingQuery;
use Mamun724682\DbGovernor\DTOs\QueryResult;
use Mamun724682\DbGovernor\DTOs\RollbackResult;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Exceptions\InvalidTransitionException;
use Mamun724682\DbGovernor\Exceptions\QueryBlockedException;
use Mamun724682\DbGovernor\Models\GovernedQuery;

class ApprovalService
{
    public function __construct(
        private readonly QueryClassifier $classifier,
        private readonly RiskAnalyzer $analyzer,
        private readonly QueryExecutor $executor,
        private readonly RollbackService $rollbackService,
        private readonly AccessGuard $guard,
        private readonly ConnectionManager $connectionManager,
        private readonly DryRunEngine $dryRunEngine,
    ) {}

    public function submit(PendingQuery $dto): GovernedQuery
    {
        if ($hiddenTable = $this->connectionManager->firstHiddenTableIn($dto->sql)) {
            throw new QueryBlockedException(["Access to table \"{$hiddenTable}\" is restricted."]);
        }

        // Pre-validate: for UPDATE/DELETE with WHERE, ensure at least one row would be affected
        $preCheckError = $this->preCheckWhereRows($dto->sql, $dto->connection);
        if ($preCheckError !== null) {
            throw new \InvalidArgumentException($preCheckError);
        }

        $type = $this->classifier->classify($dto->sql);
        $risk = $this->analyzer->analyze($dto->sql, $dto->connection);

        $attributes = [
            'connection' => $dto->connection,
            'query_table' => ($this->classifier->extractTables($dto->sql)[0] ?? null),
            'sql_raw' => $dto->sql,
            'query_type' => $type->value,
            'name' => $dto->name,
            'description' => $dto->description,
            'risk_note' => $dto->riskNote,
            'risk_level' => $risk->level->value,
            'risk_flags' => $risk->flags,
            'estimated_rows' => $risk->estimatedRows,
            'submitted_by' => $this->guard->email(),
            'submitted_ip' => request()->ip(),
        ];

        if ($risk->blocked) {
            GovernedQuery::create(array_merge($attributes, [
                'status' => QueryStatus::Blocked->value,
            ]));

            throw new QueryBlockedException($risk->flags);
        }

        return GovernedQuery::create(array_merge($attributes, [
            'status' => QueryStatus::Pending->value,
        ]));
    }

    public function approve(string $uuid, ?string $note = null): void
    {
        $this->guard->assertAdmin();

        $query = GovernedQuery::findOrFail($uuid);

        if ($query->status !== QueryStatus::Pending->value) {
            throw new InvalidTransitionException($query->status, QueryStatus::Pending->value, 'approve');
        }

        $query->update([
            'status' => QueryStatus::Approved->value,
            'reviewed_by' => $this->guard->email(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    public function reject(string $uuid, string $reason = ''): void
    {
        $this->guard->assertAdmin();

        $query = GovernedQuery::findOrFail($uuid);

        if ($query->status !== QueryStatus::Pending->value) {
            throw new InvalidTransitionException($query->status, QueryStatus::Pending->value, 'reject');
        }

        $query->update([
            'status' => QueryStatus::Rejected->value,
            'reviewed_by' => $this->guard->email(),
            'reviewed_at' => now(),
            'review_note' => $reason,
        ]);
    }

    public function execute(string $uuid): QueryResult
    {
        $this->guard->assertAdmin();
        $query = GovernedQuery::findOrFail($uuid);

        if ($hiddenTable = $this->connectionManager->firstHiddenTableIn($query->sql_raw)) {
            throw new QueryBlockedException(["Access to table \"{$hiddenTable}\" is restricted."]);
        }

        return $this->executor->executeWrite($query);
    }

    public function rollback(string $uuid): RollbackResult
    {
        $this->guard->assertAdmin();

        $query = GovernedQuery::findOrFail($uuid);

        if ($query->status !== QueryStatus::Executed->value) {
            throw new InvalidTransitionException($query->status, QueryStatus::Executed->value, 'rollback');
        }

        return $this->rollbackService->rollback($query);
    }

    /**
     * For UPDATE/DELETE with a WHERE clause, verify at least one row would be affected.
     * Uses DryRunEngine (EXPLAIN on MySQL/PostgreSQL) — the WHERE clause is never
     * re-injected into a separate query.
     *
     * Returns an error message string if validation fails, null otherwise.
     */
    private function preCheckWhereRows(string $sql, string $connectionKey): ?string
    {
        $verb = strtoupper(trim(strtok($sql, " \t\n\r") ?: ''));

        if (! in_array($verb, ['UPDATE', 'DELETE'], true)) {
            return null;
        }

        if (! preg_match('/\bWHERE\b/i', $sql)) {
            return null;
        }

        $estimated = $this->dryRunEngine->estimate($sql, $connectionKey);

        if ($estimated === 0) {
            $table = $this->classifier->extractTables($sql)[0] ?? 'unknown';

            return "Query rejected: no rows match the WHERE condition in table \"{$table}\". "
                .'Verify your filter values and resubmit.';
        }

        return null;
    }
}
