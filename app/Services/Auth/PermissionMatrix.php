<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionMatrix
{
    /**
     * @var array<string, bool>
     */
    private array $requestCache = [];

    public function allows(?User $user, string $permissionKey): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $cacheKey = implode(':', ['permission', $user->role, $permissionKey]);

        if (array_key_exists($cacheKey, $this->requestCache)) {
            return $this->requestCache[$cacheKey];
        }

        $allowed = Cache::remember(
            $cacheKey,
            now()->addMinutes(30),
            function () use ($user, $permissionKey): bool {
                $allowed = RolePermission::query()
                    ->where('role', $user->role)
                    ->where('permission_key', $permissionKey)
                    ->value('allowed');

                if ($allowed !== null) {
                    return (bool) $allowed;
                }

                return $user->isBusinessOwner() && str_starts_with($permissionKey, 'business.');
            },
        );

        return $this->requestCache[$cacheKey] = $allowed;
    }
}
