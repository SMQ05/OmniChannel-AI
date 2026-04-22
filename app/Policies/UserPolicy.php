<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canPermission('business.team.view');
    }

    public function updateRole(User $user, User $member): bool
    {
        if ($member->business_id !== $user->business_id) {
            return false;
        }

        if ($member->isBusinessOwner() || $member->id === $user->id) {
            return false;
        }

        return $user->canPermission('business.roles.manage');
    }

    public function delete(User $user, User $member): bool
    {
        if ($member->business_id !== $user->business_id) {
            return false;
        }

        if ($member->isBusinessOwner() || $member->id === $user->id) {
            return false;
        }

        return $user->canPermission('business.team.manage');
    }
}
