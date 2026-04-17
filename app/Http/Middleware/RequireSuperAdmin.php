<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate-keep the /admin routes to super_admin role only.
 *
 * Any authenticated user without role = 'super_admin' receives a 403.
 * Unauthenticated requests are caught by the 'auth' middleware upstream
 * and redirected to the login page before this middleware is reached.
 */
class RequireSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Request                 $request
     * @param  Closure(Request):Response  $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'super_admin') {
            abort(403, 'Super admin access required.');
        }

        return $next($request);
    }
}
