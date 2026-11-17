<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Routes that remain available while a password change is pending.
     *
     * @var array<int, string>
     */
    private const ALLOWED_ROUTES = [
        'api.password.change',
        'api.logout',
        'api.user',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return response()->json([
                'message' => 'You must change your password before continuing.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
