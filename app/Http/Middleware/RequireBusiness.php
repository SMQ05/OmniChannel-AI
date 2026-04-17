<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure the authenticated user belongs to a business.
 *
 * Super admins have no business_id and must not access business-scoped routes.
 * Any user whose ->business relation is null receives a 403.
 */
class RequireBusiness
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->business === null) {
            abort(403, 'This area requires a business account.');
        }

        return $next($request);
    }
}
