<?php

namespace Mamun724682\DbGovernor\Services;

use Mamun724682\DbGovernor\DTOs\{PendingQuery, QueryResult, RollbackResult};
use Mamun724682\DbGovernor\Enums\QueryStatus;
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
    ) {}

    public function submit(PendingQuery $dto): GovernedQuery
    {
        $type = $this->classifier->classify($dto->sql);
        $risk = $this->analyzer->analyze($dto->sql, $dto->connection);

        $attributes = [
            'connection'     => $dto->connection,
            'sql_raw'        => $dto->sql,
            'query_type'     => $type->value,
            'name'           => $dto->name,
            'description'    => $dto->description,
            'risk_note'      => $dto->riskNote,
            'risk_level'     => $risk->level->value,
            'risk_flags'     => $risk->flags,
            'estimated_rows' => $risk->estimatedRows,
            'submitted_by'   => $this->guard->email(),
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

        GovernedQuery::findOrFail($uuid)->update([
            'status'      => QueryStatus::Approved->value,
            'reviewed_by' => $this->guard->email(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    public function reject(string $uuid, string $reason = ''): void
    {
        $this->guard->assertAdmin();

        GovernedQuery::findOrFail($uuid)->update([
            'status'      => QueryStatus::Rejected->value,
            'reviewed_by' => $this->guard->email(),
            'reviewed_at' => now(),
            'review_note' => $reason,
        ]);
    }

    public function execute(string $uuid): QueryResult
    {
        $this->guard->assertAdmin();
        $query = GovernedQuery::findOrFail($uuid);

        return $this->executor->executeWrite($query);
    }

    public function rollback(string $uuid): RollbackResult
    {
        $this->guard->assertAdmin();
        $query = GovernedQuery::findOrFail($uuid);

        return $this->rollbackService->rollback($query);
    }
}

