<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Usage\UsageSummaryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionSettingsController extends Controller
{
    public function index(Request $request, UsageSummaryService $usageSummaryService): View
    {
        $business = $request->user()->business;

        return view('settings.subscription', [
            'business' => $business,
            'usageSummary' => $usageSummaryService->forBusiness($business),
        ]);
    }
}
