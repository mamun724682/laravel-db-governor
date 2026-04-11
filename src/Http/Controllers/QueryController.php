<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mamun724682\DbGovernor\DTOs\PendingQuery;
use Mamun724682\DbGovernor\Exceptions\QueryBlockedException;
use Mamun724682\DbGovernor\Models\GovernedQuery;
use Mamun724682\DbGovernor\Services\ApprovalService;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class QueryController
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly ConnectionManager $connectionManager,
    ) {}

    public function index(Request $request, string $token, string $connection): View
    {
        $queries           = GovernedQuery::where('connection', $connection)->latest()->paginate(20);
        $tables            = $this->connectionManager->inspector($connection)->listTables($this->connectionManager->resolve($connection));
        $currentConnection = $connection;

        return view('db-governor::queries', compact('queries', 'tables', 'token', 'currentConnection'));
    }

    public function store(Request $request, string $token, string $connection): RedirectResponse
    {
        $request->validate([
            'sql'         => ['required', 'string'],
            'name'        => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        try {
            $this->approvalService->submit(new PendingQuery(
                sql: $request->input('sql'),
                connection: $connection,
                name: $request->input('name'),
                description: $request->input('description'),
            ));
        } catch (QueryBlockedException $e) {
            return redirect()->back()->with('error', 'Query was blocked: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Query submitted for review.');
    }

    public function action(Request $request, string $token, string $connection, string $query, string $action): RedirectResponse
    {
        $note = $request->input('note', '');

        try {
            match ($action) {
                'approve'  => $this->approvalService->approve($query, $note ?: null),
                'reject'   => $this->approvalService->reject($query, $note),
                'execute'  => $this->approvalService->execute($query),
                'rollback' => $this->approvalService->rollback($query),
                default    => abort(404, "Unknown action: {$action}"),
            };
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Action '{$action}' completed.");
    }
}

