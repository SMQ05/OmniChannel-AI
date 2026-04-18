<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\VoiceSession;
use App\Services\Voice\VoiceIdempotencyService;
use App\Services\Voice\VoiceToolCatalog;
use App\Services\Voice\VoiceToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoiceToolController extends Controller
{
    private const WRITE_TOOLS = [
        'book_appointment',
        'reschedule_appointment',
        'cancel_appointment',
        'create_callback_request',
        'notify_staff',
        'send_followup_whatsapp',
    ];

    public function __construct(
        private readonly VoiceToolCatalog $voiceToolCatalog,
        private readonly VoiceToolService $voiceToolService,
        private readonly VoiceIdempotencyService $voiceIdempotencyService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'tools' => $this->voiceToolCatalog->definitions(),
        ]);
    }

    public function execute(Request $request, string $tool): JsonResponse
    {
        $toolNames = array_map(
            static fn (array $definition): string => (string) $definition['name'],
            $this->voiceToolCatalog->definitions(),
        );

        $validated = $request->validate([
            'voice_session_id' => ['required', 'integer', 'exists:voice_sessions,id'],
            'payload' => ['nullable', 'array'],
        ]);

        abort_unless(in_array($tool, $toolNames, true), 404, 'Unknown tool.');

        $voiceSession = VoiceSession::query()
            ->with(['business.subscription.plan', 'patient'])
            ->findOrFail((int) $validated['voice_session_id']);

        $payload = (array) ($validated['payload'] ?? []);

        if (in_array($tool, self::WRITE_TOOLS, true)) {
            $idempotencyKey = trim((string) ($request->header('X-Idempotency-Key') ?? $payload['idempotency_key'] ?? ''));
            abort_if($idempotencyKey === '', 422, 'X-Idempotency-Key is required for write tools.');

            $result = $this->voiceIdempotencyService->execute(
                $voiceSession,
                $tool,
                $idempotencyKey,
                fn (): array => $this->voiceToolService->execute($voiceSession, $tool, $payload),
            );

            return response()->json([
                'tool' => $tool,
                'replayed' => $result['replayed'],
                'result' => $result['result'],
            ]);
        }

        $result = $this->voiceToolService->execute($voiceSession, $tool, $payload);

        return response()->json([
            'tool' => $tool,
            'replayed' => false,
            'result' => $result,
        ]);
    }
}
