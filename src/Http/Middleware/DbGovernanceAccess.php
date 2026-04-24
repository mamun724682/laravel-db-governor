<?php

namespace Mamun724682\DbGovernor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mamun724682\DbGovernor\Services\AccessGuard;
use Mamun724682\DbGovernor\Services\ConnectionManager;
use Symfony\Component\HttpFoundation\Response;

class DbGovernanceAccess
{
    public function __construct(
        private readonly AccessGuard $guard,
        private readonly ConnectionManager $connectionManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) session('dbg_token', '');

        try {
            $payload = $this->guard->validateToken($token);
            $this->guard->setPayload($payload);
        } catch (\RuntimeException $e) {
            session()->forget('dbg_token');
            return redirect()->route('db-governor.login')
                ->with('error', $e->getMessage());
        }

        $connectionKey = $request->route('connection');

        if ($connectionKey !== null && ! $this->connectionManager->isValidKey((string) $connectionKey)) {
            return redirect()->route('db-governor.connections.pick')
                ->with('error', 'Invalid connection.');
        }

        return $next($request);
    }
}
