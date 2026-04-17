<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Automatically constrains all queries to the authenticated user's
 * business_id, providing row-level tenant isolation without
 * requiring callers to remember to add the where clause.
 *
 * Usage: add `use HasTenantScope;` to any tenant-owned model
 * and implement `static::addGlobalScope(new TenantScope())` in
 * the model's `booted()` method.
 *
 * To bypass (e.g. in super_admin or scheduler contexts):
 *   Model::withoutGlobalScope(TenantScope::class)->…
 */
final class TenantScope implements Scope
{
    /**
     * Apply the tenant scope to the given Eloquent query builder.
     *
     * The scope is skipped when:
     *  - No user is authenticated (CLI / scheduler context)
     *  - The authenticated user is a super_admin
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        if ($user->role === 'super_admin') {
            return;
        }

        if ($user->business_id !== null) {
            $builder->where(
                $model->getTable() . '.business_id',
                '=',
                $user->business_id,
            );
        }
    }
}
