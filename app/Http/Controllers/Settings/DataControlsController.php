<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\DataGovernanceRequest;
use App\Services\DataControls\GovernanceWorkflowService;
use App\Services\Diagnostics\DiagnosticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataControlsController extends Controller
{
    public function index(Request $request, DiagnosticsService $diagnosticsService): View
    {
        $this->authorize('viewAny', DataGovernanceRequest::class);

        $business = $request->user()->business;
        abort_if($business === null, 403);

        $requests = DataGovernanceRequest::query()
            ->where('business_id', $business->id)
            ->with(['requestedBy', 'approvedBy', 'executedBy'])
            ->orderByDesc('requested_at')
            ->limit(100)
            ->get();

        return view('settings/data-controls', [
            'business' => $business,
            'requests' => $requests,
            'diagnostics' => $diagnosticsService->forBusiness($business),
        ]);
    }

    public function requestExport(
        Request $request,
        GovernanceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('requestExport', DataGovernanceRequest::class);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $business = $request->user()->business;
        abort_if($business === null, 403);

        $workflowService->requestExport(
            actor: $request->user(),
            business: $business,
            filters: ['format' => 'json'],
            reason: $validated['reason'] ?? null,
        );

        return redirect()->route('settings.data-controls')
            ->with('success', 'Export request submitted for admin approval.');
    }

    public function requestDeletion(
        Request $request,
        GovernanceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('requestDeletion', DataGovernanceRequest::class);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'mode' => ['required', 'in:anonymize,hard_delete'],
        ]);

        $business = $request->user()->business;
        abort_if($business === null, 403);

        $workflowService->requestDeletion(
            actor: $request->user(),
            business: $business,
            filters: ['mode' => $validated['mode']],
            reason: $validated['reason'],
        );

        return redirect()->route('settings.data-controls')
            ->with('success', 'Deletion request submitted for admin approval.');
    }

    public function downloadArtifact(Request $request, DataGovernanceRequest $governanceRequest): StreamedResponse
    {
        $this->authorize('downloadArtifact', $governanceRequest);

        $disk = $governanceRequest->artifact_disk ?? (string) config('data_controls.exports.disk', 'local');
        $path = $governanceRequest->artifact_path;
        abort_unless($path !== null && Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path, basename($path));
    }
}
