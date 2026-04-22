<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingPortalService;
use App\Services\Billing\BillingSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BillingSettingsController extends Controller
{
    public function index(Request $request, BillingSummaryService $billingSummaryService): View
    {
        $business = $request->user()->business;

        return view('settings.billing', [
            'business' => $business,
            'billingSummary' => $billingSummaryService->forBusiness($business),
        ]);
    }

    public function portal(
        Request $request,
        BillingPortalService $billingPortalService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $business = $request->user()->business;

        try {
            $url = $billingPortalService->launch(
                business: $business,
                returnUrl: route('settings.billing'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'billing' => $exception->getMessage(),
            ]);
        }

        $auditLogger->log(
            actor: $request->user(),
            action: 'billing.portal_launched',
            subjectType: $business::class,
            subjectId: $business->id,
            payload: [
                'provider_driver' => $business->billingAccount?->provider_driver,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->away($url);
    }
}
