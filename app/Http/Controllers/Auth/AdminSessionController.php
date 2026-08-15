<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminSessionController extends Controller
{
    /**
     * Show the admin login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/AdminLogin', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming admin authentication request.
     *
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if (! $request->user()->hasAdminPortalRole()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'This account does not have access to the admin portal.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $isAdmin = $request->user()?->hasAdminPortalRole() ?? false;

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($isAdmin ? 'admin.login' : 'login');
    }
}
