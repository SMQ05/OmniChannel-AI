<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Monitoring\MonitoringSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMonitoringController extends Controller
{
    public function index(Request $request, MonitoringSnapshotService $snapshotService): View
    {
        $this->ensureMonitoringPermission($request);

        return view('admin.monitoring.index', [
            'latestPlatformSnapshot' => $snapshotService->latestPlatformSnapshot(),
            'tenantSnapshots' => $snapshotService->recentTenantSnapshots(50),
            'businesses' => Business::query()->orderBy('name')->limit(200)->get(['id', 'name']),
        ]);
    }

    public function capturePlatform(Request $request, MonitoringSnapshotService $snapshotService): RedirectResponse
    {
        $this->ensureMonitoringPermission($request);
        $snapshotService->capturePlatformSnapshot();

        return redirect()->route('admin.monitoring.index')
            ->with('success', 'Platform monitoring snapshot captured.');
    }

    public function captureTenant(Request $request, Business $business, MonitoringSnapshotService $snapshotService): RedirectResponse
    {
        $this->ensureMonitoringPermission($request);
        $snapshotService->captureTenantSnapshot($business);

        return redirect()->route('admin.monitoring.index')
            ->with('success', "Tenant snapshot captured for {$business->name}.");
    }

    private function ensureMonitoringPermission(Request $request): void
    {
        abort_unless($request->user()?->canPermission('platform.monitoring.view'), 403);
    }
}
