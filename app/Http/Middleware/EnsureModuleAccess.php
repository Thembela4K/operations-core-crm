<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a whole module against the central role map on User::MODULE_ACCESS.
 *
 * Read-only roles (business_analyst) may read gated modules but never mutate
 * them. Personal modules (tasks, projects, leave, attendance) are not gated
 * here, because every employee uses those for their own work.
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

        if ($user->isBusinessAnalyst() && ! $request->isMethodSafe()) {
            abort(403, 'This role has read-only access.');
        }

        return $next($request);
    }
}
