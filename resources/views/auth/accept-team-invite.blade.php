<x-guest-layout>
    <div class="mb-6">
        <p class="page-eyebrow">Team Invite</p>
        <h1 class="mt-2 text-2xl font-semibold text-[var(--text-strong)]">Join {{ $invite->business->name }}</h1>
        <p class="mt-2 text-sm text-[var(--text-muted)]">You were invited as {{ $assignableRoles[$invite->role] ?? ucfirst($invite->role) }}. This invite expires {{ $invite->expires_at->diffForHumans() }}.</p>
    </div>

    <form method="POST" action="{{ route('team-invites.accept', $token) }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" :value="$invite->email" disabled />
        </div>

        <div>
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center">
            {{ __('Accept Invite') }}
        </x-primary-button>
    </form>
</x-guest-layout>
