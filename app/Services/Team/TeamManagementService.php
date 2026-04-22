<?php

declare(strict_types=1);

namespace App\Services\Team;

use App\Models\Business;
use App\Models\TeamInvite;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamManagementService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array{name:string,email:string,role:string,password:string}  $data
     */
    public function acceptInvite(TeamInvite $invite, array $data, ?Request $request = null): User
    {
        if ($invite->status !== 'pending' || $invite->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'email' => 'This invite is no longer valid.',
            ]);
        }

        if (User::query()->where('email', $invite->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'A user with this email already exists.',
            ]);
        }

        return DB::transaction(function () use ($invite, $data, $request): User {
            $user = User::query()->create([
                'business_id' => $invite->business_id,
                'name' => $data['name'],
                'email' => $invite->email,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ]);

            $invite->forceFill([
                'status' => 'accepted',
                'accepted_by_user_id' => $user->id,
                'accepted_at' => now(),
                'meta' => array_merge($invite->meta ?? [], ['accepted_name' => $user->name]),
            ])->save();

            $this->auditLogger->log(
                actor: $user,
                action: 'business.team.invite.accepted',
                subjectType: TeamInvite::class,
                subjectId: $invite->id,
                payload: [
                    'email' => $invite->email,
                    'role' => $user->role,
                ],
                request: $request,
                businessId: $invite->business_id,
            );

            return $user;
        });
    }

    /**
     * @param  array{email:string,role:string}  $data
     * @return array{invite: TeamInvite, token: string}
     */
    public function issueInvite(Business $business, User $actor, array $data, ?Request $request = null): array
    {
        if (User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'invite_email' => 'A user with this email already exists.',
            ]);
        }

        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);

        $invite = TeamInvite::query()->firstOrNew([
            'business_id' => $business->id,
            'email' => $data['email'],
            'status' => 'pending',
        ]);

        $invite->fill([
            'invited_by_user_id' => $actor->id,
            'role' => $data['role'],
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(7),
            'meta' => array_merge($invite->meta ?? [], ['resent_count' => $invite->exists ? (($invite->meta['resent_count'] ?? 0)) : 0]),
        ]);
        $invite->save();

        $this->auditLogger->log(
            actor: $actor,
            action: 'business.team.invite.issued',
            subjectType: TeamInvite::class,
            subjectId: $invite->id,
            payload: [
                'email' => $invite->email,
                'role' => $invite->role,
                'expires_at' => $invite->expires_at?->toIso8601String(),
            ],
            request: $request,
            businessId: $business->id,
        );

        return ['invite' => $invite, 'token' => $token];
    }

    /**
     * @return array{invite: TeamInvite, token: string}
     */
    public function resendInvite(TeamInvite $invite, User $actor, ?Request $request = null): array
    {
        if ($invite->status !== 'pending') {
            throw ValidationException::withMessages([
                'invite' => 'Only pending invites can be resent.',
            ]);
        }

        $token = Str::random(64);

        $invite->forceFill([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'invited_by_user_id' => $actor->id,
            'meta' => array_merge($invite->meta ?? [], [
                'resent_count' => (int) (($invite->meta['resent_count'] ?? 0) + 1),
            ]),
        ])->save();

        $this->auditLogger->log(
            actor: $actor,
            action: 'business.team.invite.resent',
            subjectType: TeamInvite::class,
            subjectId: $invite->id,
            payload: [
                'email' => $invite->email,
                'role' => $invite->role,
            ],
            request: $request,
            businessId: $invite->business_id,
        );

        return ['invite' => $invite, 'token' => $token];
    }

    public function revokeInvite(TeamInvite $invite, User $actor, ?Request $request = null): void
    {
        if ($invite->status !== 'pending') {
            throw ValidationException::withMessages([
                'invite' => 'Only pending invites can be revoked.',
            ]);
        }

        $invite->forceFill([
            'status' => 'revoked',
        ])->save();

        $this->auditLogger->log(
            actor: $actor,
            action: 'business.team.invite.revoked',
            subjectType: TeamInvite::class,
            subjectId: $invite->id,
            payload: [
                'email' => $invite->email,
                'role' => $invite->role,
            ],
            request: $request,
            businessId: $invite->business_id,
        );
    }

    public function updateMemberRole(User $member, string $role, User $actor, ?Request $request = null): void
    {
        $previousRole = $member->role;

        $member->update([
            'role' => $role,
        ]);

        $this->auditLogger->log(
            actor: $actor,
            action: 'business.team.member.role_updated',
            subjectType: User::class,
            subjectId: $member->id,
            payload: [
                'email' => $member->email,
                'previous_role' => $previousRole,
                'new_role' => $role,
            ],
            request: $request,
            businessId: $member->business_id,
        );
    }

    public function removeMember(User $member, User $actor, ?Request $request = null): void
    {
        $payload = [
            'email' => $member->email,
            'role' => $member->role,
            'name' => $member->name,
        ];

        $businessId = $member->business_id;
        $subjectId = $member->id;

        $member->delete();

        $this->auditLogger->log(
            actor: $actor,
            action: 'business.team.member.removed',
            subjectType: User::class,
            subjectId: $subjectId,
            payload: $payload,
            request: $request,
            businessId: $businessId,
        );
    }

    public function resolvePendingInvite(string $token): ?TeamInvite
    {
        return TeamInvite::query()
            ->with('business')
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'pending')
            ->first();
    }
}
