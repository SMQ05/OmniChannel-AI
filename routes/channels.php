<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('business.{businessId}.handoffs', function (User $user, int $businessId): bool {
    if ($user->isSuperAdmin()) {
        return true;
    }

    return $user->business_id === $businessId
        && $user->canPermission('business.conversations.view');
});
