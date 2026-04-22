<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TeamInvite;
use App\Models\User;
use App\Services\Team\TeamManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamManagementController extends Controller
{
    public function __construct(
        private readonly TeamManagementService $teamManagementService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);
        $this->authorize('viewAny', TeamInvite::class);

        $business = $request->user()->business;

        return view('settings.team', [
            'business' => $business,
            'members' => $business->users()->orderByRaw("CASE WHEN role = 'business_owner' THEN 0 ELSE 1 END")->orderBy('name')->get(),
            'pendingInvites' => $business->teamInvites()->where('status', 'pending')->latest()->get(),
            'recentAuditLogs' => $business->auditLogs()->latest('created_at')->limit(12)->get(),
            'inviteLink' => session('team_invite_link'),
            'assignableRoles' => User::assignableBusinessRoles(),
        ]);
    }

    public function storeInvite(Request $request): RedirectResponse
    {
        $this->authorize('create', TeamInvite::class);

        $validated = $request->validate([
            'invite_email' => ['required', 'email:rfc', 'max:255'],
            'invite_role' => ['required', Rule::in(array_keys(User::assignableBusinessRoles()))],
        ]);

        $result = $this->teamManagementService->issueInvite(
            business: $request->user()->business,
            actor: $request->user(),
            data: [
                'email' => strtolower($validated['invite_email']),
                'role' => $validated['invite_role'],
            ],
            request: $request,
        );

        return redirect()->route('settings.team')
            ->with('success', 'Team invite created.')
            ->with('team_invite_link', route('team-invites.show', $result['token']));
    }

    public function resendInvite(Request $request, TeamInvite $teamInvite): RedirectResponse
    {
        $this->authorize('resend', $teamInvite);

        $result = $this->teamManagementService->resendInvite($teamInvite, $request->user(), $request);

        return redirect()->route('settings.team')
            ->with('success', 'Invite link refreshed.')
            ->with('team_invite_link', route('team-invites.show', $result['token']));
    }

    public function destroyInvite(Request $request, TeamInvite $teamInvite): RedirectResponse
    {
        $this->authorize('delete', $teamInvite);

        $this->teamManagementService->revokeInvite($teamInvite, $request->user(), $request);

        return redirect()->route('settings.team')->with('success', 'Invite revoked.');
    }

    public function updateMemberRole(Request $request, User $user): RedirectResponse
    {
        $this->authorize('updateRole', $user);

        $validated = $request->validate([
            'role' => ['required', Rule::in(array_keys(User::assignableBusinessRoles()))],
        ]);

        $this->teamManagementService->updateMemberRole($user, $validated['role'], $request->user(), $request);

        return redirect()->route('settings.team')->with('success', 'Team member role updated.');
    }

    public function destroyMember(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $this->teamManagementService->removeMember($user, $request->user(), $request);

        return redirect()->route('settings.team')->with('success', 'Team member removed.');
    }
}
