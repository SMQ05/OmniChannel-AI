<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ConversationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;

/**
 * Renders the super-admin system dashboard at /admin.
 *
 * Shows:
 *  - Platform-wide stats: total businesses, total messages today, active AI sessions
 *  - Queue depth for each named queue (webhooks | ai | reminders | integrations)
 *  - Link to Laravel Horizon
 */
class AdminDashboardController extends Controller
{
    /**
     * Render the admin dashboard.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $totalBusinesses    = Business::query()->count();
        $activeBusinesses   = Business::query()->where('is_active', true)->count();
        $messagesToday      = ConversationLog::query()
            ->whereDate('created_at', today())
            ->count();
        $activeAiSessions   = ConversationLog::query()
            ->where('human_mode', false)
            ->whereNull('session_ended_at')
            ->whereDate('updated_at', today())
            ->count();
        $pendingHandoffs    = ConversationLog::query()
            ->where('human_mode', true)
            ->whereNull('session_ended_at')
            ->count();

        // Queue depths — read from Redis via Laravel Queue manager
        $queueDepths = $this->getQueueDepths();

        // Recent businesses
        $recentBusinesses = Business::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'totalBusinesses'  => $totalBusinesses,
            'activeBusinesses' => $activeBusinesses,
            'messagesToday'    => $messagesToday,
            'activeAiSessions' => $activeAiSessions,
            'pendingHandoffs'  => $pendingHandoffs,
            'queueDepths'      => $queueDepths,
            'recentBusinesses' => $recentBusinesses,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Read queue depths from Redis for each named queue.
     *
     * Falls back to null per queue if Redis is unreachable.
     *
     * @return array<string, int|null>
     */
    private function getQueueDepths(): array
    {
        $queues = ['webhooks', 'ai', 'reminders', 'integrations'];
        $depths = [];

        foreach ($queues as $queue) {
            try {
                $depths[$queue] = (int) Queue::size($queue);
            } catch (\Throwable) {
                $depths[$queue] = null;
            }
        }

        return $depths;
    }
}
