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

    public function index(Request $request, string $connection): View
    {
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

        $tables = $this->connectionManager->listTables($connection);
        $currentConnection = $connection;

        return view('db-governor::dashboard', compact('stats', 'tables', 'currentConnection'));
    }
}
