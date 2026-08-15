<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to users holding any of the given organization roles.
 *
 * Usage: ->middleware('role:super_admin,ssc_head')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $roles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->filter()
            ->map(fn (string $role) => trim($role))
            ->all();

        if (! $user->hasRole($roles)) {
            abort(403);
        }

        return $next($request);
    }
}
