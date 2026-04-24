<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mamun724682\DbGovernor\Services\ConnectionManager;

class ConnectionController
{
    public function __construct(private readonly ConnectionManager $connectionManager) {}

    public function pick(Request $request): View|RedirectResponse
    {
        $connections = $this->connectionManager->all();

        if (count($connections) === 1) {
            $key = array_key_first($connections);

            return redirect()->route('db-governor.dashboard', [
                'connection' => $key,
            ]);
        }

        return view('db-governor::connections', compact('connections'));
    }
}
