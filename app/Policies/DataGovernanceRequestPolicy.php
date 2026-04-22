<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DataGovernanceRequest;
use App\Models\User;

class DataGovernanceRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->canPermission('business.data_controls.view');
    }

    public function view(User $user, DataGovernanceRequest $request): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->business_id === null || $request->business_id === null) {
            return false;
        }

        return $user->business_id === $request->business_id
            && $user->canPermission('business.data_controls.view');
    }

    public function requestExport(User $user): bool
    {
        return $user->canPermission('business.data_export.request');
    }

    public function requestDeletion(User $user): bool
    {
        return $user->canPermission('business.data_delete.request');
    }

    public function approve(User $user, DataGovernanceRequest $request): bool
    {
        return $user->isSuperAdmin()
            && $user->canPermission('platform.compliance.approve')
            && $request->status === DataGovernanceRequest::STATUS_PENDING_APPROVAL;
    }

    public function execute(User $user, DataGovernanceRequest $request): bool
    {
        return $user->isSuperAdmin()
            && $user->canPermission('platform.compliance.execute')
            && in_array($request->status, [DataGovernanceRequest::STATUS_APPROVED, DataGovernanceRequest::STATUS_FAILED], true);
    }

    public function downloadArtifact(User $user, DataGovernanceRequest $request): bool
    {
        if ($request->status !== DataGovernanceRequest::STATUS_COMPLETED || $request->artifact_path === null || $request->artifactIsExpired()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return $user->canPermission('platform.compliance.view');
        }

        return $request->business_id !== null
            && $request->business_id === $user->business_id
            && $user->canPermission('business.data_controls.view');
    }
}
