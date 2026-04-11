<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Mamun724682\DbGovernor\Enums\QueryStatus;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class DashboardController
{
    public function __construct(private readonly ConnectionManager $connectionManager) {}

    public function index(Request $request, string $token, string $connection): View
    {
        $stats = [
            'pending'     => GovernedQuery::where('connection', $connection)->where('status', QueryStatus::Pending->value)->count(),
            'approved'    => GovernedQuery::where('connection', $connection)->where('status', QueryStatus::Approved->value)->count(),
            'executed'    => GovernedQuery::where('connection', $connection)->where('status', QueryStatus::Executed->value)->count(),
            'rejected'    => GovernedQuery::where('connection', $connection)->where('status', QueryStatus::Rejected->value)->count(),
            'rolled_back' => GovernedQuery::where('connection', $connection)->where('status', QueryStatus::RolledBack->value)->count(),
            'blocked'     => GovernedQuery::where('connection', $connection)->where('status', QueryStatus::Blocked->value)->count(),
        ];

        $tables            = $this->connectionManager->inspector($connection)->listTables($this->connectionManager->resolve($connection));
        $currentConnection = $connection;

        return view('db-governor::dashboard', compact('stats', 'tables', 'token', 'currentConnection'));
    }
}

