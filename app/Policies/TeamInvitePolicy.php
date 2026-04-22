<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TeamInvite;
use App\Models\User;

class TeamInvitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canPermission('business.team.view');
    }

    public function create(User $user): bool
    {
        return $user->canPermission('business.team.manage');
    }

    public function resend(User $user, TeamInvite $invite): bool
    {
        return $invite->business_id === $user->business_id
            && $invite->status === 'pending'
            && $user->canPermission('business.team.manage');
    }

    public function delete(User $user, TeamInvite $invite): bool
    {
        return $invite->business_id === $user->business_id
            && $invite->status === 'pending'
            && $user->canPermission('business.team.manage');
    }
}
