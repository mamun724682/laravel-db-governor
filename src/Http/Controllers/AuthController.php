<?php

namespace Mamun724682\DbGovernor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        $key = 'dbg_login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($key);

            return redirect()->route('db-governor.login')
                ->with('error', "Too many login attempts. Please try again in {$seconds} seconds.");
        }

        $token = $this->guard->login($request->input('email'));

        if ($token === null) {
            RateLimiter::hit($key, decaySeconds: 60);

            return redirect()->route('db-governor.login')
                ->with('error', 'This email is not authorised to access DB Governor.');
        }

        RateLimiter::clear($key);
        session(['dbg_token' => $token]);

        return redirect()->route('db-governor.connections.pick');
    }

    public function logout(Request $request): RedirectResponse
    {
        $token = (string) session('dbg_token', '');
        $this->guard->revokeToken($token);
        session()->forget('dbg_token');

        return redirect()->route('db-governor.login')
            ->with('success', 'You have been logged out.');
    }
}
