<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Allows a super_admin to impersonate a business_owner without their password.
 *
 * Flow:
 *  1. POST /admin/businesses/{business}/impersonate
 *     - Stores the super_admin's user ID in the session under 'impersonating_as'
 *     - Logs in as the business's owner
 *     - Redirects to the tenant dashboard
 *
 *  2. POST /admin/impersonate/stop
 *     - Restores the original super_admin session
 *     - Redirects back to /admin
 *
 * Security:
 *  - Only super_admin can initiate — enforced by RequireSuperAdmin middleware on the route.
 *  - The 'stop' route checks that an active impersonation is in progress before swapping back.
 *  - All impersonation start/stop events are logged via Log::info().
 */
class AdminImpersonateController extends Controller
{
    /**
     * Begin impersonating the primary business_owner of the given business.
     *
     * @param  Request   $request
     * @param  Business  $business
     * @return RedirectResponse
     */
    public function start(Request $request, Business $business): RedirectResponse
    {
        $superAdmin = $request->user();
        $redirectTo = $request->string('redirect_to')->toString();

        // Find the business_owner for this tenant
        $owner = User::query()
            ->where('business_id', $business->id)
            ->where('role', 'business_owner')
            ->firstOrFail();

        // Store the super_admin ID so we can restore them later
        Session::put('impersonating_as', $superAdmin->id);

        Log::info('AdminImpersonateController: impersonation started.', [
            'super_admin_id' => $superAdmin->id,
            'target_user_id' => $owner->id,
            'business_id'    => $business->id,
        ]);

        Auth::login($owner);

        if (!str_starts_with($redirectTo, '/settings/')) {
            $redirectTo = route('dashboard');
        }

        return redirect()->to($redirectTo)
            ->with('info', "Now impersonating {$owner->name} ({$business->name}). Use the stop button to return to admin.");
    }

    /**
     * Stop impersonating and restore the original super_admin session.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function stop(Request $request): RedirectResponse
    {
        $originalId = Session::pull('impersonating_as');

        if (!$originalId) {
            return redirect()->route('dashboard');
        }

        $superAdmin = User::find($originalId);

        if (!$superAdmin || $superAdmin->role !== 'super_admin') {
            // Session corrupt — log out entirely for safety
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login');
        }

        Log::info('AdminImpersonateController: impersonation stopped.', [
            'super_admin_id'    => $superAdmin->id,
            'impersonated_user' => $request->user()?->id,
        ]);

        Auth::login($superAdmin);

        return redirect()->route('admin.dashboard');
    }
}
