<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessDataRetentionSetting;
use App\Models\DataGovernanceRequest;
use App\Services\DataControls\GovernanceWorkflowService;
use App\Services\DataControls\RetentionPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminComplianceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->canPermission('platform.compliance.view'), 403);

        $governanceRequests = DataGovernanceRequest::query()
            ->with(['business', 'requestedBy', 'approvedBy', 'executedBy'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('request_type', $request->input('type')))
            ->orderByDesc('requested_at')
            ->paginate(50)
            ->withQueryString();

        $retentionSettings = BusinessDataRetentionSetting::query()
            ->with(['business', 'updatedBy'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return view('admin.compliance.index', [
            'governanceRequests' => $governanceRequests,
            'retentionSettings' => $retentionSettings,
            'businesses' => Business::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'retentionDefaults' => config('data_controls.retention.defaults', []),
            'retentionBounds' => config('data_controls.retention.bounds', []),
            'filters' => $request->only(['status', 'type']),
        ]);
    }

    public function show(Request $request, DataGovernanceRequest $governanceRequest): View
    {
        abort_unless($request->user()?->canPermission('platform.compliance.view'), 403);
        $this->authorize('view', $governanceRequest);

        return view('admin.compliance.show', [
            'governanceRequest' => $governanceRequest->load(['business', 'requestedBy', 'approvedBy', 'executedBy']),
        ]);
    }

    public function approve(
        Request $request,
        DataGovernanceRequest $governanceRequest,
        GovernanceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('approve', $governanceRequest);

        $validated = $request->validate([
            'approval_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $workflowService->approve($request->user(), $governanceRequest, $validated['approval_reason'] ?? null);
        $workflowService->dispatchExecution($governanceRequest->refresh(), $request->user());

        return redirect()->route('admin.compliance.show', $governanceRequest)
            ->with('success', 'Governance request approved and queued for execution.');
    }

    public function reject(
        Request $request,
        DataGovernanceRequest $governanceRequest,
        GovernanceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('approve', $governanceRequest);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $workflowService->reject($request->user(), $governanceRequest, $validated['rejection_reason']);

        return redirect()->route('admin.compliance.show', $governanceRequest)
            ->with('success', 'Governance request rejected.');
    }

    public function run(
        Request $request,
        DataGovernanceRequest $governanceRequest,
        GovernanceWorkflowService $workflowService,
    ): RedirectResponse {
        $this->authorize('execute', $governanceRequest);
        $workflowService->dispatchExecution($governanceRequest, $request->user());

        return redirect()->route('admin.compliance.show', $governanceRequest)
            ->with('success', 'Governance execution queued.');
    }

    public function updateRetention(
        Request $request,
        Business $business,
        RetentionPolicyService $retentionPolicyService,
    ): RedirectResponse {
        abort_unless($request->user()?->canPermission('platform.retention.manage'), 403);

        $min = (int) config('data_controls.retention.bounds.min_days', 30);
        $max = (int) config('data_controls.retention.bounds.max_days', 1460);

        $validated = $request->validate([
            'conversation_logs_days' => ['nullable', 'integer', "between:{$min},{$max}"],
            'inbound_webhooks_days' => ['nullable', 'integer', "between:{$min},{$max}"],
            'outbound_attempts_days' => ['nullable', 'integer', "between:{$min},{$max}"],
            'voice_events_days' => ['nullable', 'integer', "between:{$min},{$max}"],
            'deletion_strategy' => ['required', 'in:anonymize,hard_delete'],
            'legal_hold_until' => ['nullable', 'date'],
        ]);

        BusinessDataRetentionSetting::query()->updateOrCreate(
            ['business_id' => $business->id],
            [
                ...$validated,
                'updated_by_user_id' => $request->user()->id,
                'legal_hold_until' => $validated['legal_hold_until'] ?? null,
            ],
        );

        // Prime computed policy after update to surface bounded behavior in UI logs.
        $retentionPolicyService->effectivePolicyForBusiness($business);

        return redirect()->route('admin.compliance.index')
            ->with('success', "Retention settings updated for {$business->name}.");
    }

    public function runRetention(
        Request $request,
        RetentionPolicyService $retentionPolicyService,
    ): RedirectResponse {
        abort_unless($request->user()?->canPermission('platform.retention.manage'), 403);

        $validated = $request->validate([
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'dry_run' => ['nullable', 'boolean'],
            'run_key' => ['nullable', 'uuid'],
        ]);

        $business = isset($validated['business_id'])
            ? Business::query()->find((int) $validated['business_id'])
            : null;

        $runKey = $validated['run_key'] ?? (string) Str::uuid();
        $retentionPolicyService->runRetention(
            business: $business,
            dryRun: (bool) ($validated['dry_run'] ?? false),
            runKey: $runKey,
            commandSignature: 'http:admin.compliance.retention.run',
            actor: $request->user(),
        );

        return redirect()->route('admin.compliance.index')
            ->with('success', 'Retention run logged with run key: ' . $runKey);
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
