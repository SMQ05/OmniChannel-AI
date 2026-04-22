<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\PermissionMatrix;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private readonly PermissionMatrix $permissionMatrix,
    ) {
    }

    public function handle(Request $request, Closure $next, string $permissionKey): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Authentication required.');
        }

        if (!$this->permissionMatrix->allows($user, $permissionKey)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
