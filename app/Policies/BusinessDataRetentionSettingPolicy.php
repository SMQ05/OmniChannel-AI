<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BusinessDataRetentionSetting;
use App\Models\User;

class BusinessDataRetentionSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() && $user->canPermission('platform.compliance.view');
    }

    public function update(User $user, BusinessDataRetentionSetting $setting): bool
    {
        return $user->isSuperAdmin() && $user->canPermission('platform.retention.manage');
    }
}
