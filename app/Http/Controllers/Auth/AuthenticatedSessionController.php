<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\BranchContext;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        $ldapEnabled = (bool) config('ldap.enabled', false);
        $ldapReady = $ldapEnabled && function_exists('ldap_connect');

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'supportEmail' => config('mail.from.address'),
            'authStatus' => [
                'label' => $ldapReady ? 'Local + LDAP ready' : ($ldapEnabled ? 'Local ready, LDAP unavailable' : 'Local authentication ready'),
                'tone' => $ldapReady ? 'ready' : ($ldapEnabled ? 'warning' : 'ready'),
            ],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $branch = app(BranchContext::class)->branch($request->user());
        $destination = $branch?->code === 'KL-IT'
            ? route('it-assets.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($destination);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
