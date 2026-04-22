<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\IncidentBanner;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminIncidentsController - Admin control plane for incident banner management.
 *
 * This controller provides:
 *  - Create, edit, publish, archive incident banners
 *  - Platform-wide and business-specific incidents
 *  - Severity management (info, warning, critical)
 *  - Display window scheduling
 *
 * Incident banners are visible on both admin and business sides
 * without implying centralization of runtime behavior.
 */
class AdminIncidentsController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Render the incidents overview page.
     *
     * Shows all incident banners with their status and scope.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = IncidentBanner::query()
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('scope')) {
            if ($request->input('scope') === 'platform') {
                $query->where('is_platform_wide', true);
            } else {
                $query->where('is_platform_wide', false);
            }
        }

        $incidents = $query->paginate(50)->withQueryString();
        $severities = ['info', 'warning', 'critical'];
        $statuses = ['draft', 'published', 'archived', 'resolved'];

        return view('admin.incidents.index', [
            'incidents' => $incidents,
            'severities' => $severities,
            'statuses' => $statuses,
            'filters' => $request->only(['status', 'severity', 'scope']),
        ]);
    }

    /**
     * Show the create incident form.
     *
     * @return View
     */
    public function create(): View
    {
        $businesses = Business::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('admin.incidents.create', [
            'businesses' => $businesses,
        ]);
    }

    /**
     * Store a new incident banner.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string'],
            'severity' => ['required', 'string', 'in:info,warning,critical'],
            'is_platform_wide' => ['required', 'boolean'],
            'business_id' => ['nullable', 'exists:businesses,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // If platform-wide, business_id should be null
        if ($validated['is_platform_wide']) {
            $validated['business_id'] = null;
        }

        $incident = IncidentBanner::create([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'severity' => $validated['severity'],
            'is_platform_wide' => $validated['is_platform_wide'],
            'business_id' => $validated['business_id'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'status' => 'draft',
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'incident.created',
            subjectType: IncidentBanner::class,
            subjectId: $incident->id,
            payload: [
                'title' => $incident->title,
                'severity' => $incident->severity,
                'is_platform_wide' => $incident->is_platform_wide,
                'business_id' => $incident->business_id,
                'starts_at' => $incident->starts_at?->toIso8601String(),
                'ends_at' => $incident->ends_at?->toIso8601String(),
            ],
            request: $request,
            businessId: $incident->business_id,
        );

        return redirect()->route('admin.incidents.index')
            ->with('success', 'Incident banner created.');
    }

    /**
     * Show the edit incident form.
     *
     * @param  IncidentBanner  $incident
     * @return View
     */
    public function edit(IncidentBanner $incident): View
    {
        $businesses = Business::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('admin.incidents.edit', [
            'incident' => $incident,
            'businesses' => $businesses,
        ]);
    }

    /**
     * Update an incident banner.
     *
     * @param  Request  $request
     * @param  IncidentBanner  $incident
     * @return RedirectResponse
     */
    public function update(Request $request, IncidentBanner $incident): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string'],
            'severity' => ['required', 'string', 'in:info,warning,critical'],
            'is_platform_wide' => ['required', 'boolean'],
            'business_id' => ['nullable', 'exists:businesses,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $originalIsPlatformWide = $incident->is_platform_wide;

        // If platform-wide, business_id should be null
        if ($validated['is_platform_wide']) {
            $validated['business_id'] = null;
        }

        $incident->update([
            'title' => $validated['title'],
            'message' => $validated['message'],
            'severity' => $validated['severity'],
            'is_platform_wide' => $validated['is_platform_wide'],
            'business_id' => $validated['business_id'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
        ]);

        // If scope changed from platform to business or vice versa, audit that
        if ($originalIsPlatformWide !== $incident->is_platform_wide) {
            $this->auditLogger->log(
                actor: $request->user(),
                action: 'incident.scope_changed',
                subjectType: IncidentBanner::class,
                subjectId: $incident->id,
                payload: [
                    'previous_scope' => $originalIsPlatformWide ? 'platform' : 'business',
                    'new_scope' => $incident->is_platform_wide ? 'platform' : 'business',
                    'business_id' => $incident->business_id,
                ],
                request: $request,
                businessId: $incident->business_id,
            );
        }

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'incident.updated',
            subjectType: IncidentBanner::class,
            subjectId: $incident->id,
            payload: [
                'title' => $incident->title,
                'severity' => $incident->severity,
                'starts_at' => $incident->starts_at?->toIso8601String(),
                'ends_at' => $incident->ends_at?->toIso8601String(),
            ],
            request: $request,
            businessId: $incident->business_id,
        );

        return redirect()->route('admin.incidents.index')
            ->with('success', 'Incident banner updated.');
    }

    /**
     * Publish an incident banner.
     *
     * @param  Request  $request
     * @param  IncidentBanner  $incident
     * @return RedirectResponse
     */
    public function publish(Request $request, IncidentBanner $incident): RedirectResponse
    {
        $request->validate([
            'publish_now' => ['accepted'],
        ]);

        $incident->update([
            'status' => 'published',
            'published_by_user_id' => $request->user()->id,
            'published_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'incident.published',
            subjectType: IncidentBanner::class,
            subjectId: $incident->id,
            payload: [
                'title' => $incident->title,
                'published_by_user_id' => $request->user()->id,
                'published_at' => now()->toIso8601String(),
            ],
            request: $request,
            businessId: $incident->business_id,
        );

        return redirect()->route('admin.incidents.index')
            ->with('success', 'Incident banner published.');
    }

    /**
     * Archive an incident banner.
     *
     * @param  Request  $request
     * @param  IncidentBanner  $incident
     * @return RedirectResponse
     */
    public function archive(Request $request, IncidentBanner $incident): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $incident->update([
            'status' => 'archived',
            'archived_by_user_id' => $request->user()->id,
            'archived_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'incident.archived',
            subjectType: IncidentBanner::class,
            subjectId: $incident->id,
            payload: [
                'title' => $incident->title,
                'reason' => $request->input('reason'),
                'archived_by_user_id' => $request->user()->id,
                'archived_at' => now()->toIso8601String(),
            ],
            request: $request,
            businessId: $incident->business_id,
        );

        return redirect()->route('admin.incidents.index')
            ->with('success', 'Incident banner archived.');
    }

    /**
     * Resolve an incident banner.
     *
     * @param  Request  $request
     * @param  IncidentBanner  $incident
     * @return RedirectResponse
     */
    public function resolve(Request $request, IncidentBanner $incident): RedirectResponse
    {
        $request->validate([
            'resolution_message' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $incident->update([
            'status' => 'resolved',
            'resolved_by_user_id' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'incident.resolved',
            subjectType: IncidentBanner::class,
            subjectId: $incident->id,
            payload: [
                'title' => $incident->title,
                'resolution_message' => $request->input('resolution_message'),
                'resolved_by_user_id' => $request->user()->id,
                'resolved_at' => now()->toIso8601String(),
            ],
            request: $request,
            businessId: $incident->business_id,
        );

        return redirect()->route('admin.incidents.index')
            ->with('success', 'Incident banner resolved.');
    }

    /**
     * Delete an incident banner.
     *
     * @param  Request  $request
     * @param  IncidentBanner  $incident
     * @return RedirectResponse
     */
    public function destroy(Request $request, IncidentBanner $incident): RedirectResponse
    {
        $request->validate([
            'confirm_delete' => ['accepted'],
        ]);

        $incident->delete();

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'incident.deleted',
            subjectType: IncidentBanner::class,
            subjectId: $incident->id,
            payload: [
                'title' => $incident->title,
                'status_at_delete' => $incident->status,
            ],
            request: $request,
            businessId: $incident->business_id,
        );

        return redirect()->route('admin.incidents.index')
            ->with('success', 'Incident banner deleted.');
    }

    /**
     * Get active incidents for a business.
     *
     * @param  Business  $business
     * @return array<string, mixed>
     */
    public function activeForBusiness(Business $business): array
    {
        $incidents = IncidentBanner::query()
            ->where('status', 'published')
            ->where(function ($q) use ($business) {
                // Platform-wide incidents
                $q->where('is_platform_wide', true)
                  ->orWhere('business_id', $business->id);
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>', now());
            })
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'message', 'severity', 'is_platform_wide']);

        return [
            'platform_incidents' => $incidents->where('is_platform_wide', true)->toArray(),
            'business_incidents' => $incidents->where('is_platform_wide', false)->toArray(),
        ];
    }
}
