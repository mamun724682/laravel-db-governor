<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mamun724682\DbGovernor\DTOs\PendingQuery;
use Mamun724682\DbGovernor\Exceptions\QueryBlockedException;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ApprovalService;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class QueryController
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly ConnectionManager $connectionManager,
        private readonly AccessGuard $guard,
    ) {}

    public function index(Request $request, string $token, string $connection): View
    {
        $isAdmin = $this->guard->isAdmin();
        $tab = $request->input('tab', 'write');

        $queryBuilder = GovernedQuery::where('connection', $connection);

        // Tab filtering: write tab shows write/ddl/unknown; read tab shows read only
        if ($tab === 'read') {
            $queryBuilder->where('query_type', 'read');
        } else {
            $queryBuilder->whereIn('query_type', ['write', 'ddl', 'unknown']);
        }

        if (! $isAdmin) {
            // Employees only see their own submissions
            $queryBuilder->where('submitted_by', $this->guard->email());
        } elseif ($request->filled('submitted_by')) {
            $queryBuilder->where('submitted_by', $request->input('submitted_by'));
        }

        if ($request->filled('status')) {
            $queryBuilder->where('status', $request->input('status'));
        }

        if ($request->filled('query_type')) {
            $queryBuilder->where('query_type', $request->input('query_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $queryBuilder->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sql_raw', 'like', "%{$search}%");
            });
        }

        if ($request->filled('keyword')) {
            $kw = $request->input('keyword');
            $queryBuilder->where(function ($q) use ($kw) {
                $q->where('sql_raw', 'like', "%{$kw}%")
                    ->orWhere('name', 'like', "%{$kw}%")
                    ->orWhere('description', 'like', "%{$kw}%");
            });
        }

        if ($request->filled('date_from')) {
            $queryBuilder->where('created_at', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $queryBuilder->where('created_at', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
        }

        $queries = $queryBuilder->latest()->simplePaginate(25)->withQueryString();
        $tables = $this->connectionManager->listTables($connection);
        $currentConnection = $connection;
        $submitters = $isAdmin
            ? array_values(array_unique(array_map('strtolower', array_merge(
                config('db-governor.allowed.admins', []),
                config('db-governor.allowed.employees', []),
            ))))
            : [];

        return view('db-governor::queries', compact(
            'queries', 'tables', 'token', 'currentConnection', 'isAdmin', 'submitters', 'tab'
        ));
    }

    public function store(Request $request, string $token, string $connection): RedirectResponse
    {
        $request->validate([
            'sql' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'risk_note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->approvalService->submit(new PendingQuery(
                sql: $request->input('sql'),
                connection: $connection,
                name: $request->input('name'),
                description: $request->input('description'),
                riskNote: $request->input('risk_note'),
            ));
        } catch (QueryBlockedException $e) {
            return redirect()->back()->with('error', 'Query was blocked: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Query submitted for review.');
    }

    public function action(Request $request, string $token, string $connection, string $query, string $action): RedirectResponse
    {
        // Accept 'note', 'review_note', or 'rejection_reason' field names
        $note = $request->input('note') ?? $request->input('review_note') ?? $request->input('rejection_reason') ?? '';

        try {
            match ($action) {
                'approve' => $this->approvalService->approve($query, $note ?: null),
                'reject' => $this->approvalService->reject($query, $note),
                'execute' => $this->approvalService->execute($query),
                'rollback' => $this->approvalService->rollback($query),
                default => abort(404, "Unknown action: {$action}"),
            };
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Action '{$action}' completed.");
    }
}
