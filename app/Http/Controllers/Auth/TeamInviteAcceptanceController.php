<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Team\TeamManagementService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamInviteAcceptanceController extends Controller
{
    public function __construct(
        private readonly TeamManagementService $teamManagementService,
    ) {
    }

    public function show(string $token): View
    {
        $invite = $this->teamManagementService->resolvePendingInvite($token);

        abort_if($invite === null || $invite->expires_at->isPast(), 404);

        return view('auth.accept-team-invite', [
            'invite' => $invite,
            'token' => $token,
            'assignableRoles' => User::assignableBusinessRoles(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        abort_if(Auth::check(), 403, 'Log out before accepting a team invite.');

        $invite = $this->teamManagementService->resolvePendingInvite($token);

        abort_if($invite === null || $invite->expires_at->isPast(), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = $this->teamManagementService->acceptInvite(
            invite: $invite,
            data: [
                'name' => $validated['name'],
                'email' => $invite->email,
                'password' => $validated['password'],
                'role' => $invite->role,
            ],
            request: $request,
        );

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome to your team workspace.');
    }
}
