<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a whole module against the central role map on User::MODULE_ACCESS.
 *
 * The business admin is the administrator role and is no longer read-only, so
 * this middleware only checks module entry. Personal modules (tasks, projects,
 * leave, attendance) are not gated here, because every employee uses those for
 * their own work.
 */
class EnsureModuleAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! $user->canAccessModule($module)) {
            abort(403);
        }

        return $next($request);
    }
}
