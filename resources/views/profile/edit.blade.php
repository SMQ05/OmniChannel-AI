<x-layouts.app title="Profile & Security">
<div class="mx-auto max-w-5xl space-y-6">
    <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
        <h2 class="text-xl font-semibold text-white">Profile & Security</h2>
        <p class="mt-1 text-sm text-gray-400">Update your clinic login details, rotate your password, and manage account security from the same app shell as the rest of the SaaS.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            @include('profile.partials.update-password-form')
        </div>
    </div>

    <div class="rounded-2xl border border-red-500/20 bg-gray-900/60 p-6">
        @include('profile.partials.delete-user-form')
    </div>
</div>
</x-layouts.app>
