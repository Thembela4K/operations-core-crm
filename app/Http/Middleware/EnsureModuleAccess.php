<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a whole module against the central role map on User::MODULE_ACCESS.
 *
 * Business admins are read-only everywhere except staff administration, where
 * they may see and add users. Personal modules (tasks, projects, leave,
 * attendance) are not gated here, because every employee uses those for their
 * own work.
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

        $readOnlyException = $module === 'users' && $user->canManageUsers();

        if ($user->isBusinessAdmin() && ! $readOnlyException && ! $request->isMethodSafe()) {
            abort(403, 'This role has read-only access.');
        }

        return $next($request);
    }
}
