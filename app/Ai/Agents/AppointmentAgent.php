<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Business;
use App\Models\ConversationLog;
use App\Services\Operations\BusinessServiceCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Facades\Ai;

/**
 * AppointmentAgent — the AI layer of the booking pipeline.
 *
 * Responsibilities:
 *  1. Build a fully-rendered system prompt from the tenant's ai_config.
 *  2. Convert UTC slots to the business's local timezone for patient-
 *     facing labels embedded in the prompt.
 *  3. Invoke the tenant-configured LLM via the Laravel AI SDK.
 *  4. Parse and validate the structured JSON response.
 *  5. Return a typed AgentResponse array for the orchestrator to act on.
 *
 * Timezone contract:
 *  - Slots received from SlotCalculatorService carry start_utc / end_utc
 *    ISO strings. This class converts them to $business->timezone for
 *    the prompt labels so the patient sees local times.
 *  - The date/time fields in the returned AgentResponse are LOCAL to the
 *    business timezone. AppointmentOrchestrator converts them to UTC
 *    before writing to the database.
 *
 * Provider selection contract:
 *  - Slots are already constrained to exactly 7 rolling days and
 *    capped at MAX_SLOTS by SlotCalculatorService. This class formats
 *    them but never modifies the set.
 *
 * LLM provider mapping:
 *  - 'claude'     → claude-sonnet-4-6   (default)
 *  - 'gpt4o'      → gpt-4o
 *  - 'openrouter' → openrouter/openai/gpt-4.1-mini
 *  - 'minimax'    → minimax-text-01
 *
 * @phpstan-type AgentResponse array{
 *     intent:       string,
 *     provider_id:  int|null,
 *     service_id:   int|null,
 *     date:         string|null,
 *     time:         string|null,
 *     service_type: string|null,
 *     reply_text:   string,
 *     needs_human:  bool,
 * }
 *
 * @phpstan-type SlotArray array{
 *     provider_id:   int,
 *     provider_name: string,
 *     date:          string,
 *     start_utc:     string,
 *     end_utc:       string,
 *     label:         string,
 * }
 */
class AppointmentAgent
{
    public function __construct(
        private readonly ?BusinessServiceCatalog $businessServiceCatalog = null,
    ) {
    }

    /** Valid intent values the LLM is permitted to return. */
    private const VALID_INTENTS = ['book', 'cancel', 'reschedule', 'faq', 'handoff'];

    /** Fallback message when the AI call fails entirely. */
    private const FALLBACK_REPLY = "Sorry, I'm having a moment! Please contact us directly to book.";

    /**
     * Invoke the AI agent for one conversation turn.
     *
     * @param  Business          $business        The active tenant
     * @param  ConversationLog   $conversationLog The current session (messages already include latest user turn)
     * @param  list<SlotArray>   $availableSlots  UTC slots from SlotCalculatorService (capped at MAX_SLOTS)
     * @param  string            $inboundText     The patient's raw message (used for logging only)
     * @return AgentResponse
     */
    public function handle(
        Business $business,
        ConversationLog $conversationLog,
        array $availableSlots,
        string $inboundText,
    ): array {
        return $this->respond(
            business: $business,
            messages: $conversationLog->toAiMessages(),
            availableSlots: $availableSlots,
            providerOverride: null,
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<SlotArray>  $availableSlots
     * @return AgentResponse
     */
    public function respond(
        Business $business,
        array $messages,
        array $availableSlots = [],
        ?string $providerOverride = null,
    ): array {
        try {
            $systemPrompt = $this->buildSystemPrompt($business, $availableSlots);
            $provider = $providerOverride ?: $business->llmProvider();
            $model = $this->resolveModel($provider);

            $rawResponse = Ai::chat()
                ->model($model)
                ->system($systemPrompt)
                ->messages($messages)
                ->send();

            $agentResponse = $this->parseResponse((string) $rawResponse);

            Log::info('AppointmentAgent: turn complete.', [
                'business_id' => $business->id,
                'intent'      => $agentResponse['intent'],
                'model'       => $model,
            ]);

            return $agentResponse;

        } catch (\Throwable $e) {
            Log::error('AppointmentAgent: AI call failed.', [
                'business_id' => $business->id,
                'model'       => $this->resolveModel($providerOverride ?: $business->llmProvider()),
                'error'       => $e->getMessage(),
            ]);

            return $this->fallbackResponse($business);
        }
    }

    // -------------------------------------------------------------------------
    // System Prompt Builder
    // -------------------------------------------------------------------------

    /**
     * Compile the full system prompt from the tenant's ai_config and the
     * computed slot list.
     *
     * Slots are converted from UTC to the business's local timezone here so
     * the patient-facing prompt shows meaningful local times. The underlying
     * start_utc / end_utc values are preserved in the raw slot array for the
     * orchestrator to use when writing to the DB.
     *
     * @param  Business         $business
     * @param  list<SlotArray>  $availableSlots
     */
    private function buildSystemPrompt(Business $business, array $availableSlots): string
    {
        $config   = $business->ai_config ?? [];
        $timezone = $business->timezone;

        $aiName      = $config['ai_name']  ?? 'AI Assistant';
        $persona     = $config['persona']  ?? '';
        $tone        = $config['tone']     ?? 'friendly';
        $language    = $config['language'] ?? 'English';
        $services    = ($this->businessServiceCatalog ?? app(BusinessServiceCatalog::class))->promptCatalog($business);
        $faqs        = $config['faqs']     ?? [];

        return implode("\n\n", array_filter([
            $this->sectionIdentity($aiName, $business->name, $business->business_type, $persona),
            $this->sectionTask(),
            $this->sectionProviders($availableSlots),
            $this->sectionSlots($availableSlots, $timezone),
            $this->sectionServices($services),
            $this->sectionFaqs($faqs),
            $this->sectionRules($tone, $language),
            $this->sectionOutputFormat(),
        ]));
    }

    /**
     * Build the AI identity and persona section.
     *
     * @param  string  $aiName
     * @param  string  $businessName
     * @param  string  $businessType
     * @param  string  $persona
     */
    private function sectionIdentity(
        string $aiName,
        string $businessName,
        string $businessType,
        string $persona,
    ): string {
        $lines = [
            "You are {$aiName}, a professional AI receptionist for {$businessName}, a {$businessType}.",
        ];

        if ($persona !== '') {
            $lines[] = $persona;
        }

        return implode("\n", $lines);
    }

    /**
     * Build the task description section.
     */
    private function sectionTask(): string
    {
        return "YOUR TASK: Help customers book, cancel, or reschedule appointments, and answer questions about the business.";
    }

    /**
     * Build the available providers section from the slot list.
     *
     * Deduplicated — each provider appears once even if they have many slots.
     *
     * @param  list<SlotArray>  $slots
     */
    private function sectionProviders(array $slots): string
    {
        if (empty($slots)) {
            return "AVAILABLE PROVIDERS:\nNo providers are currently available.";
        }

        $seen      = [];
        $providers = [];

        foreach ($slots as $slot) {
            if (!isset($seen[$slot['provider_id']])) {
                $seen[$slot['provider_id']] = true;
                $providers[] = "- {$slot['provider_name']} (ID: {$slot['provider_id']})";
            }
        }

        return "AVAILABLE PROVIDERS:\n" . implode("\n", $providers);
    }

    /**
     * Build the available slots section, converting UTC times to the
     * business's local timezone for patient-facing display.
     *
     * Format per slot:
     *   [Provider Name] — Mon 20 Apr, 09:00 – 09:30 (slot_id: X)
     *
     * The bracketed slot index is a compact reference the model can use
     * in its response to identify a specific slot unambiguously.
     *
     * @param  list<SlotArray>  $slots
     * @param  string           $timezone  Business timezone
     */
    private function sectionSlots(array $slots, string $timezone): string
    {
        if (empty($slots)) {
            return "AVAILABLE APPOINTMENT SLOTS (next 7 days):\nNo slots available in the next 7 days.";
        }

        $lines = [];

        foreach ($slots as $index => $slot) {
            $startLocal = Carbon::parse($slot['start_utc'])->setTimezone($timezone);
            $endLocal   = Carbon::parse($slot['end_utc'])->setTimezone($timezone);

            $lines[] = sprintf(
                '  [%d] %s — %s, %s – %s',
                $index,
                $slot['provider_name'],
                $startLocal->format('D d M'),
                $startLocal->format('H:i'),
                $endLocal->format('H:i'),
            );
        }

        return "AVAILABLE APPOINTMENT SLOTS (next 7 days):\n" . implode("\n", $lines);
    }

    /**
     * Build the services section from structured services when available,
     * falling back to legacy ai_config.services without changing behavior.
     *
     * @param  list<array{name: string, duration_min: int, price?: int|float, id?: int}>  $services
     */
    private function sectionServices(array $services): string
    {
        if (empty($services)) {
            return '';
        }

        $lines = [];

        foreach ($services as $service) {
            $name     = $service['name']         ?? 'Service';
            $duration = $service['duration_min'] ?? 30;
            $price    = isset($service['price']) ? ' — ' . number_format((float) $service['price'], 0) : '';
            $identifier = isset($service['id']) ? "[ID {$service['id']}] " : '';
            $lines[]  = "- {$identifier}{$name} ({$duration} min{$price})";
        }

        return "SERVICES OFFERED:\n" . implode("\n", $lines);
    }

    /**
     * Build the FAQ section from ai_config.faqs.
     *
     * @param  list<array{q: string, a: string}>  $faqs
     */
    private function sectionFaqs(array $faqs): string
    {
        if (empty($faqs)) {
            return '';
        }

        $lines = [];

        foreach ($faqs as $faq) {
            $lines[] = "Q: {$faq['q']}\nA: {$faq['a']}";
        }

        return "FREQUENTLY ASKED QUESTIONS:\n" . implode("\n\n", $lines);
    }

    /**
     * Build the tone, language, and behavioural rules section.
     *
     * @param  string  $tone      'formal' | 'friendly' | 'casual'
     * @param  string  $language  e.g. 'English', 'Urdu', 'Arabic'
     */
    private function sectionRules(string $tone, string $language): string
    {
        return implode("\n", [
            "LANGUAGE: Always respond in {$language}.",
            "TONE: {$tone} - formal|friendly|casual.",
            '',
            'RULES:',
            '1. NEVER invent slots. Only use slots from AVAILABLE SLOTS above.',
            '2. If needs_human is true, stop handling and escalate immediately.',
            '3. Collect: patient name, preferred date/time, service type, and provider (if preference given).',
            '4. If a service has a listed ID, return that same value in service_id whenever you can identify the requested service.',
            '5. ALWAYS reply ONLY with valid raw JSON. No markdown. No preamble. No explanation outside JSON.',
            '6. Dates and times in your JSON response must use the local business timezone, not UTC.',
        ]);
    }

    /**
     * Build the required JSON output format section.
     */
    private function sectionOutputFormat(): string
    {
        return <<<'FORMAT'
        REQUIRED JSON OUTPUT FORMAT (respond with ONLY this JSON — no other text):
        {
          "intent":       "book|cancel|reschedule|faq|handoff",
          "provider_id":  null or integer,
          "service_id":   null or integer,
          "date":         null or "YYYY-MM-DD",
          "time":         null or "HH:MM",
          "service_type": null or string,
          "reply_text":   string,
          "needs_human":  boolean
        }
        FORMAT;
    }

    // -------------------------------------------------------------------------
    // Response Parser
    // -------------------------------------------------------------------------

    /**
     * Parse and validate the raw LLM response string into an AgentResponse.
     *
     * The model is instructed to return only raw JSON. This method:
     *  1. Strips any accidental markdown code fences.
     *  2. JSON-decodes the string.
     *  3. Validates that all required keys are present.
     *  4. Normalises types to match the AgentResponse shape.
     *
     * Throws \RuntimeException when the response cannot be parsed so the
     * caller can catch and fall back gracefully.
     *
     * @param  string  $raw  The raw string returned by the LLM
     * @return AgentResponse
     *
     * @throws \RuntimeException
     */
    private function parseResponse(string $raw): array
    {
        // Strip markdown code fences if the model wrapped its JSON
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $cleaned = preg_replace('/\s*```$/i', '', $cleaned ?? '');

        $data = json_decode($cleaned ?? '', associative: true);

        if (!is_array($data)) {
            throw new \RuntimeException(
                'AppointmentAgent: LLM did not return valid JSON. Raw: ' . Str::limit($raw, 200),
            );
        }

        $intent = (string) ($data['intent'] ?? 'faq');

        if (!in_array($intent, self::VALID_INTENTS, true)) {
            Log::warning('AppointmentAgent: unrecognised intent, defaulting to faq.', [
                'intent' => $intent,
            ]);
            $intent = 'faq';
        }

        return [
            'intent'       => $intent,
            'provider_id'  => isset($data['provider_id']) ? (int) $data['provider_id'] : null,
            'service_id'   => isset($data['service_id']) ? (int) $data['service_id'] : null,
            'date'         => isset($data['date'])         ? (string) $data['date']     : null,
            'time'         => isset($data['time'])         ? (string) $data['time']     : null,
            'service_type' => isset($data['service_type']) ? (string) $data['service_type'] : null,
            'reply_text'   => (string) ($data['reply_text'] ?? self::FALLBACK_REPLY),
            'needs_human'  => (bool)   ($data['needs_human'] ?? false),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Map a tenant LLM provider slug to the concrete model identifier
     * expected by the Laravel AI SDK.
     *
     * @param  string  $provider  'claude' | 'gpt4o' | 'openrouter' | 'minimax'
     */
    private function resolveModel(string $provider): string
    {
        return match (strtolower($provider)) {
            'gpt4o'   => 'gpt-4o',
            'openrouter' => 'openrouter/openai/gpt-4.1-mini',
            'minimax' => 'minimax-text-01',
            default   => 'claude-sonnet-4-6',
        };
    }

    /**
     * Produce a safe fallback AgentResponse when the AI call fails.
     *
     * Uses the business phone from ai_config when available so the
     * patient has an actionable contact route.
     *
     * @param  Business  $business
     * @return AgentResponse
     */
    private function fallbackResponse(Business $business): array
    {
        $phone   = $business->ai_config['business_phone'] ?? '';
        $message = $phone !== ''
            ? "Sorry, I'm having a moment! Please call us directly at {$phone} to book."
            : self::FALLBACK_REPLY;

        return [
            'intent'       => 'faq',
            'provider_id'  => null,
            'service_id'   => null,
            'date'         => null,
            'time'         => null,
            'service_type' => null,
            'reply_text'   => $message,
            'needs_human'  => false,
        ];
    }
}
