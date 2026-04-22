<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ConversationNote;
use App\Models\ConversationLog;
use App\Services\Conversations\HumanHandoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages the conversation log viewer and human takeover.
 *
 * All queries are automatically scoped to the authenticated tenant via TenantScope.
 */
class ConversationController extends Controller
{
    /**
     * Render the searchable, filterable conversation list.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = ConversationLog::query()
            ->with(['patient', 'outboundAttempts'])
            ->orderByDesc('updated_at');

        if ($request->filled('search')) {
            $search = '%' . strtolower((string) $request->input('search')) . '%';
            $query->where(function ($builder) use ($search): void {
                $builder->whereHas('patient', function ($q) use ($search): void {
                    $q->whereRaw('LOWER(name) LIKE ?', [$search])
                        ->orWhereRaw('LOWER(COALESCE(platform_user_id, \'\')) LIKE ?', [$search]);
                });
            });
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        if ($request->input('handoff_only') === '1') {
            $query->where('human_mode', true);
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('conversations.index', [
            'logs'     => $logs,
            'filters'  => $request->only(['search', 'channel', 'date', 'handoff_only']),
        ]);
    }

    /**
     * Render the full chat-bubble conversation view for a single log.
     *
     * @param  Request          $request
     * @param  ConversationLog  $conversationLog
     * @return View
     */
    public function show(Request $request, ConversationLog $conversationLog): View
    {
        $conversationLog->loadMissing(['patient', 'appointment', 'outboundAttempts', 'notes.user']);

        return view('conversations.show', [
            'log'      => $conversationLog,
            'timezone' => $request->user()->business->timezone,
        ]);
    }

    /**
     * Activate human takeover for the given conversation session.
     *
     * Sets human_mode on the ConversationLog and writes a Redis flag so
     * ProcessIncomingMessage can fast-path without a DB hit on the next message.
     *
     * @param  Request          $request
     * @param  ConversationLog  $conversationLog
     * @return JsonResponse|RedirectResponse
     */
    public function takeover(
        Request $request,
        ConversationLog $conversationLog,
        HumanHandoffService $humanHandoffService,
    ): JsonResponse|RedirectResponse {
        $conversationLog->loadMissing(['patient']);
        $business = $request->user()->business;
        $patient = $conversationLog->patient;

        $humanHandoffService->activate(
            business: $business,
            patient: $patient,
            conversationLog: $conversationLog,
            source: 'manual_takeover',
        );

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'human_mode' => true]);
        }

        return redirect()->back()->with('success', 'Human takeover activated. AI is paused for this session.');
    }

    public function storeNote(Request $request, ConversationLog $conversationLog): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        ConversationNote::query()->create([
            'conversation_log_id' => $conversationLog->id,
            'user_id' => $request->user()->id,
            'note' => $validated['note'],
        ]);

        return redirect()->route('conversations.show', $conversationLog)->with('success', 'Internal note added.');
    }
}
