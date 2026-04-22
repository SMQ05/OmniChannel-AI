<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\IncidentBanner;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminAuditLogController - Admin control plane for audit log viewing.
 *
 * This controller provides:
 *  - Search and filter audit logs
 *  - Export audit logs
 *  - View detailed audit trail for specific records
 *
 * Expands on existing audit logging for:
 *  - Impersonation start/stop
 *  - Support actions
 *  - Credential metadata changes
 *  - Rotation records
 *  - Launch stage changes
 *  - Incident publish/archive/resolve
 *  - AI policy changes
 */
class AdminAuditLogController extends Controller
{
    /**
     * Render the audit log overview page.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = AuditLog::query()
            ->with(['actorUser', 'business'])
            ->orderByDesc('created_at');

        // Filters
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_user_id', $request->input('actor_id'));
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->input('business_id'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }

        // Common action filters
        $actionFilters = [
            'impersonation' => 'impersonation',
            'support' => 'support.',
            'credential' => 'credential',
            'launch' => 'launch',
            'incident' => 'incident',
            'ai_policy' => 'ai_policy',
            'billing' => 'billing',
        ];

        if ($request->filled('action_category')) {
            $category = $request->input('action_category');
            if ($category === 'impersonation') {
                $query->where('action', 'like', 'impersonation.%');
            } elseif ($category === 'support') {
                $query->where('action', 'like', 'support.%');
            } elseif ($category === 'credential') {
                $query->where('action', 'like', 'credential.%');
            } elseif ($category === 'launch') {
                $query->where('action', 'like', 'launch.%');
            } elseif ($category === 'incident') {
                $query->where('action', 'like', 'incident.%');
            } elseif ($category === 'ai_policy') {
                $query->where('action', 'like', 'ai_policy.%');
            } elseif ($category === 'billing') {
                $query->where('action', 'like', 'billing.%');
            }
        }

        $logs = $query->paginate(100)->withQueryString();

        // Get distinct actions for filter dropdown
        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->pluck('action');

        // Get businesses for filter dropdown
        $businesses = Business::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => $actions,
            'businesses' => $businesses,
            'filters' => $request->only([
                'action', 'actor_id', 'business_id', 'subject_type',
                'date_from', 'date_to', 'action_category',
            ]),
            'actionFilters' => $actionFilters,
        ]);
    }

    /**
     * View a specific audit log entry.
     *
     * @param  AuditLog  $auditLog
     * @return View
     */
    public function show(AuditLog $auditLog): View
    {
        return view('admin.audit-logs.show', [
            'auditLog' => $auditLog,
        ]);
    }

    /**
     * Search audit logs by request ID.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchByRequestId(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'request_id' => ['required', 'uuid'],
        ]);

        $logs = AuditLog::query()
            ->where('request_id', $request->input('request_id'))
            ->with(['actorUser', 'business'])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'request_id' => $request->input('request_id'),
            'logs' => $logs,
            'count' => $logs->count(),
        ]);
    }

    /**
     * Export audit logs as CSV.
     *
     * @param  Request  $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $query = AuditLog::query()
            ->with(['actorUser', 'business']);

        // Apply same filters as index
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_user_id', $request->input('actor_id'));
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->input('business_id'));
        }

        $logs = $query->get();

        $csv = "id,business_id,business_name,actor_user_id,actor_email,actor_role,action,subject_type,subject_id,request_id,ip_address,user_agent,payload,created_at\n";

        foreach ($logs as $log) {
            $row = [
                $log->id,
                $log->business_id ?? '',
                $log->business?->name ?? '',
                $log->actor_user_id ?? '',
                $log->actorUser?->email ?? '',
                $log->actor_role ?? '',
                $log->action,
                $log->subject_type,
                $log->subject_id ?? '',
                $log->request_id ?? '',
                $log->ip_address ?? '',
                '"' . str_replace('"', '""', $log->user_agent ?? '') . '"',
                '"' . str_replace('"', '""', json_encode($log->payload)) . '"',
                $log->created_at?->toIso8601String() ?? '',
            ];
            $csv .= implode(',', $row) . "\n";
        }

        $filename = 'audit-logs-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get audit trail for a specific business.
     *
     * @param  Business  $business
     * @return \Illuminate\Http\JsonResponse
     */
    public function forBusiness(Business $business): \Illuminate\Http\JsonResponse
    {
        $logs = AuditLog::query()
            ->where('business_id', $business->id)
            ->with(['actorUser'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'business_id' => $business->id,
            'business_name' => $business->name,
            'logs' => $logs,
            'total' => $logs->count(),
        ]);
    }

    /**
     * Get audit trail for a specific subject type and ID.
     *
     * @param  string  $subjectType
     * @param  int  $subjectId
     * @return \Illuminate\Http\JsonResponse
     */
    public function forSubject(string $subjectType, int $subjectId): \Illuminate\Http\JsonResponse
    {
        $logs = AuditLog::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->with(['actorUser', 'business'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'logs' => $logs,
            'total' => $logs->count(),
        ]);
    }

    /**
     * Get recent system-wide audit events.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function recent(Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = $request->input('limit', 50);

        $logs = AuditLog::query()
            ->with(['actorUser', 'business'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'recent_logs' => $logs,
            'total' => $logs->count(),
        ]);
    }
}
