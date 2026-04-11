<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Mamun724682\DbGovernor\Services\AccessGuard;

class AuthController
{
    public function __construct(private readonly AccessGuard $guard) {}

    public function showLogin(): View
    {
        return view('db-governor::login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $token = $this->guard->login($request->input('email'));

        if ($token === null) {
            return redirect()->route('db-governor.login')
                ->with('error', 'This email is not authorised to access DB Governor.');
        }

        return redirect()->route('db-governor.connections.pick', ['token' => $token]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $token = (string) $request->route('token');
        $this->guard->revokeToken($token);

        return redirect()->route('db-governor.login')
            ->with('success', 'You have been logged out.');
    }
}

