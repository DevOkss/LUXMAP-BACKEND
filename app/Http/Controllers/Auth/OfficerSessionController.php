<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OfficerLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OfficerSessionController extends Controller
{
    /**
     * Show the officer login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/OfficerLogin', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming officer authentication request.
     *
     * @throws ValidationException
     */
    public function store(OfficerLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if (! $request->user()->hasStaffRole()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'student_number' => 'This account does not have officer access.',
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
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
