<x-layouts.app title="Team">
<div class="mx-auto max-w-6xl space-y-6">
    <x-ui.page-header
        eyebrow="Phase 2"
        title="RBAC and Team Management"
        description="Manage team members, role assignments, and invite links inside the business workspace. The shared platform brain still governs the broader control plane; this page only manages tenant-side access."
    />

    @if($inviteLink)
        <div class="panel border border-emerald-500/20 p-5" x-data="{ copied: false }">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-[var(--text-strong)]">Invite link ready</h2>
                    <p class="mt-1 text-sm text-[var(--text-muted)]">Email delivery is still deferred, so Phase 2 exposes a real copyable invite link instead of a placeholder mail flow.</p>
                </div>
                <button type="button" class="btn-secondary min-h-11 px-4 py-2 text-sm" @click="navigator.clipboard.writeText($refs.link.value); copied = true;">
                    <span x-text="copied ? 'Copied' : 'Copy invite link'"></span>
                </button>
            </div>
            <input x-ref="link" type="text" readonly value="{{ $inviteLink }}" class="field mt-4 font-mono text-xs">
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-6">
            <div class="panel p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-[var(--text-strong)]">Team members</h2>
                        <p class="mt-1 text-sm text-[var(--text-muted)]">{{ $members->count() }} active members in {{ $business->name }}.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    @foreach($members as $member)
                        <div class="panel-subtle p-4">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3">
                                        <p class="truncate text-sm font-semibold text-[var(--text-strong)]">{{ $member->name }}</p>
                                        @if($member->id === auth()->id())
                                            <span class="rounded-full bg-[var(--brand-soft)] px-2 py-0.5 text-xs font-medium text-[var(--brand-strong)]">You</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-[var(--text-muted)]">{{ $member->email }}</p>
                                    <p class="mt-2 text-xs uppercase tracking-[0.18em] text-[var(--text-soft)]">{{ $member->roleLabel() }}</p>
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    @can('updateRole', $member)
                                        <form method="POST" action="{{ route('settings.team.members.role', $member) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" class="field min-w-44 text-sm">
                                                @foreach($assignableRoles as $role => $label)
                                                    <option value="{{ $role }}" @selected($member->role === $role)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn-secondary min-h-11 px-4 py-2 text-sm">Update role</button>
                                        </form>
                                    @endcan

                                    @can('delete', $member)
                                        <form method="POST" action="{{ route('settings.team.members.destroy', $member) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger min-h-11 px-4 py-2 text-sm" onclick="return confirm('Remove this team member?');">Remove</button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-[var(--text-strong)]">Recent access audit</h2>
                        <p class="mt-1 text-sm text-[var(--text-muted)]">Team and role actions are recorded here to keep access changes legible.</p>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($recentAuditLogs as $log)
                        <div class="panel-subtle p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-medium text-[var(--text-strong)]">{{ str_replace('.', ' / ', $log->action) }}</p>
                                    <p class="mt-1 text-sm text-[var(--text-muted)]">
                                        {{ $log->actorUser?->name ?? 'System' }} · {{ $log->actor_role ?? 'n/a' }}
                                    </p>
                                </div>
                                <p class="text-xs uppercase tracking-[0.18em] text-[var(--text-soft)]">{{ $log->created_at?->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="panel-subtle p-4 text-sm text-[var(--text-muted)]">No access audit entries yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="panel p-6">
                <h2 class="text-lg font-semibold text-[var(--text-strong)]">Invite team member</h2>
                <p class="mt-1 text-sm text-[var(--text-muted)]">Issue a 7-day invite for a manager, receptionist, or staff account.</p>

                @can('create', App\Models\TeamInvite::class)
                    <form method="POST" action="{{ route('settings.team.invites.store') }}" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="invite_email" class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-[var(--text-soft)]">Email</label>
                            <input id="invite_email" type="email" name="invite_email" value="{{ old('invite_email') }}" class="field" required>
                            @error('invite_email')<p class="mt-2 text-sm text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="invite_role" class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-[var(--text-soft)]">Role</label>
                            <select id="invite_role" name="invite_role" class="field" required>
                                @foreach($assignableRoles as $role => $label)
                                    <option value="{{ $role }}" @selected(old('invite_role') === $role)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn-primary min-h-11 w-full px-4 py-2 text-sm">Create invite</button>
                    </form>
                @else
                    <div class="mt-5 panel-subtle p-4 text-sm text-[var(--text-muted)]">Your role can view the team roster but cannot issue or revoke invites.</div>
                @endcan
            </div>

            <div class="panel p-6">
                <h2 class="text-lg font-semibold text-[var(--text-strong)]">Pending invites</h2>
                <p class="mt-1 text-sm text-[var(--text-muted)]">Pending invites can be refreshed or revoked before they are accepted.</p>

                <div class="mt-5 space-y-4">
                    @forelse($pendingInvites as $invite)
                        <div class="panel-subtle p-4">
                            <p class="text-sm font-semibold text-[var(--text-strong)]">{{ $invite->email }}</p>
                            <p class="mt-1 text-sm text-[var(--text-muted)]">{{ $assignableRoles[$invite->role] ?? ucfirst($invite->role) }}</p>
                            <p class="mt-2 text-xs uppercase tracking-[0.18em] text-[var(--text-soft)]">Expires {{ $invite->expires_at->diffForHumans() }}</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @can('resend', $invite)
                                    <form method="POST" action="{{ route('settings.team.invites.resend', $invite) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="btn-secondary min-h-11 px-4 py-2 text-sm">Refresh link</button>
                                    </form>
                                @endcan

                                @can('delete', $invite)
                                    <form method="POST" action="{{ route('settings.team.invites.destroy', $invite) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-danger min-h-11 px-4 py-2 text-sm" onclick="return confirm('Revoke this invite?');">Revoke</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <div class="panel-subtle p-4 text-sm text-[var(--text-muted)]">No pending invites.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
</x-layouts.app>
