<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\BusinessService;
use App\Models\ConversationLog;
use App\Models\Provider;
use App\Services\Usage\UsageSummaryService;
use App\Services\Voice\VoiceConfigurationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders the main SaaS dashboard and serves the live-stats JSON endpoint.
 *
 * All counts are scoped to the authenticated user's business via TenantScope.
 * The /dashboard/stats endpoint is polled every 30 seconds by Alpine.js
 * to refresh stat cards and the live appointments feed without a full page reload.
 */
class DashboardController extends Controller
{
    /**
     * Render the main dashboard view.
     *
     * Passes initial data server-side so the page renders immediately without
     * waiting for Alpine.js to fire its first poll.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(
        Request $request,
        UsageSummaryService $usageSummaryService,
        VoiceConfigurationService $voiceConfigurationService,
    ): View|RedirectResponse
    {
        // Super admin has no business — redirect to the admin panel.
        if ($request->user()->role === 'super_admin') {
            return redirect()->route('admin.dashboard');
        }

        abort_unless($request->user()->canPermission('business.dashboard.view'), 403);

        $business = $request->user()->business;
        $timezone = $business->timezone;
        $now      = Carbon::now($timezone);

        $todayStart = $now->copy()->startOfDay()->utc();
        $todayEnd   = $now->copy()->endOfDay()->utc();
        $weekEnd    = $now->copy()->endOfWeek()->utc();

        $todayAppointments = Appointment::query()
            ->with(['patient', 'provider', 'service'])
            ->whereBetween('start_time', [$todayStart, $todayEnd])
            ->whereIn('status', ['confirmed', 'completed'])
            ->orderBy('start_time')
            ->get();

        $stats = $this->buildStats($business->id, $now);

        $pendingHandoffs = ConversationLog::query()
            ->where('human_mode', true)
            ->whereNull('session_ended_at')
            ->with(['patient'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $recentConversations = ConversationLog::query()
            ->with(['patient'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        return view('dashboard.index', [
            'business'            => $business,
            'stats'               => $stats,
            'todayAppointments'   => AppointmentResource::collection($todayAppointments),
            'pendingHandoffs'     => $pendingHandoffs,
            'recentConversations' => $recentConversations,
            'usageSummary'        => $usageSummaryService->forBusiness($business),
            'timezone'            => $timezone,
            'voiceState'          => $voiceConfigurationService->forBusiness($business),
            'operationsOverview'  => $this->buildOperationsOverview($business),
        ]);
    }

    /**
     * Return current stats as JSON for Alpine.js polling (every 30 seconds).
     *
     * Includes today's bookings, this week's bookings, pending handoffs,
     * messages handled today, and the live appointments feed.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        if ($request->user()->role === 'super_admin') {
            return response()->json(['error' => 'not applicable'], 403);
        }

        abort_unless($request->user()->canPermission('business.dashboard.view'), 403);

        $business = $request->user()->business;
        $timezone = $business->timezone;
        $now      = Carbon::now($timezone);

        $stats = $this->buildStats($business->id, $now);

        $todayStart = $now->copy()->startOfDay()->utc();
        $todayEnd   = $now->copy()->endOfDay()->utc();

        $todayAppointments = Appointment::query()
            ->with(['patient', 'provider', 'service'])
            ->whereBetween('start_time', [$todayStart, $todayEnd])
            ->whereIn('status', ['confirmed', 'completed'])
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'stats'             => $stats,
            'today_appointments' => AppointmentResource::collection($todayAppointments),
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build the statistics array for the dashboard stat cards.
     *
     * @param  int     $businessId
     * @param  Carbon  $now         Current time in business timezone
     * @return array<string, int>
     */
    private function buildStats(int $businessId, Carbon $now): array
    {
        $todayStart = $now->copy()->startOfDay()->utc();
        $todayEnd   = $now->copy()->endOfDay()->utc();
        $weekStart  = $now->copy()->startOfWeek()->utc();
        $weekEnd    = $now->copy()->endOfWeek()->utc();

        return [
            'bookings_today'    => Appointment::query()
                ->whereBetween('start_time', [$todayStart, $todayEnd])
                ->whereIn('status', ['confirmed', 'completed'])
                ->count(),

            'bookings_this_week' => Appointment::query()
                ->whereBetween('start_time', [$weekStart, $weekEnd])
                ->whereIn('status', ['confirmed', 'completed'])
                ->count(),

            'pending_handoffs'  => ConversationLog::query()
                ->where('human_mode', true)
                ->whereNull('session_ended_at')
                ->count(),

            'messages_today'    => ConversationLog::query()
                ->whereDate('created_at', $now->toDateString())
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOperationsOverview(\App\Models\Business $business): array
    {
        $activeServices = BusinessService::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->withCount('providers')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $providers = Provider::query()
            ->where('is_active', true)
            ->withCount('services')
            ->orderBy('name')
            ->get();

        $servicesWithoutProviders = $activeServices->filter(fn (BusinessService $service): bool => $service->providers_count === 0);
        $providersWithoutServices = $providers->filter(fn (Provider $provider): bool => $provider->services_count === 0);
        $bookingRules = $business->bookingRules();

        return [
            'active_services_count' => $activeServices->count(),
            'mapped_providers_count' => $providers->filter(fn (Provider $provider): bool => $provider->services_count > 0)->count(),
            'services_without_providers_count' => $servicesWithoutProviders->count(),
            'providers_without_services_count' => $providersWithoutServices->count(),
            'services_without_providers' => $servicesWithoutProviders->pluck('name')->values()->all(),
            'providers_without_services' => $providersWithoutServices->pluck('name')->values()->all(),
            'has_structured_services' => $activeServices->isNotEmpty(),
            'legacy_services_count' => count($business->ai_config['services'] ?? []),
            'booking_rules' => [
                'lead_time_minutes' => (int) ($bookingRules['lead_time_minutes'] ?? 0),
                'max_advance_days' => (int) ($bookingRules['max_advance_days'] ?? 30),
                'allow_same_day_booking' => (bool) ($bookingRules['allow_same_day_booking'] ?? true),
                'require_provider_selection' => (bool) ($bookingRules['require_provider_selection'] ?? false),
            ],
        ];
    }
}
