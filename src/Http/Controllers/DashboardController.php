<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class DashboardController
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly AccessGuard $guard,
    ) {}

    public function index(Request $request, string $token, string $connection): View
    {
        $baseQuery = fn () => GovernedQuery::where('connection', $connection)
            ->when(! $this->guard->isAdmin(), fn ($q) => $q->where('submitted_by', $this->guard->email()));

        $stats = [
            'pending'     => (clone $baseQuery())->where('status', QueryStatus::Pending->value)->count(),
            'approved'    => (clone $baseQuery())->where('status', QueryStatus::Approved->value)->count(),
            'executed'    => (clone $baseQuery())->where('status', QueryStatus::Executed->value)->count(),
            'rejected'    => (clone $baseQuery())->where('status', QueryStatus::Rejected->value)->count(),
            'rolled_back' => (clone $baseQuery())->where('status', QueryStatus::RolledBack->value)->count(),
            'blocked'     => (clone $baseQuery())->where('status', QueryStatus::Blocked->value)->count(),
        ];

        $tables = $this->connectionManager->listTables($connection);
        $currentConnection = $connection;

        return view('db-governor::dashboard', compact('stats', 'tables', 'token', 'currentConnection'));
    }
}
