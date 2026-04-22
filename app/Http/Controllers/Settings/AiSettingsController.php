<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Operations\BusinessServiceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages the AI configuration panel (/settings/ai).
 *
 * Persists the entire ai_config JSON to businesses.ai_config.
 * The preview endpoint compiles the current system prompt and returns
 * it as plain text for the read-only live preview block.
 */
class AiSettingsController extends Controller
{
    public function __construct(
        private readonly BusinessServiceCatalog $businessServiceCatalog,
    ) {
    }

    /**
     * Render the AI settings / training panel.
     *
     * @param  Request  $request
     * @return View
     */
    public function edit(Request $request): View
    {
        $business = $request->user()->business;
        $aiConfig = $business->ai_config ?? [];
        $structuredServices = $this->businessServiceCatalog->activeForBusiness($business);

        return view('settings.ai', [
            'business' => $business,
            'aiConfig' => $aiConfig,
            'managedByAdmin' => $this->managedByAdmin($request),
            'compiledPrompt' => $this->compilePrompt($business, $aiConfig),
            'structuredServices' => $structuredServices,
            'legacyServiceFallbackCount' => count($aiConfig['services'] ?? []),
        ]);
    }

    /**
     * Persist updated ai_config to the business record.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ai_name'      => ['required', 'string', 'max:100'],
            'persona'      => ['required', 'string', 'max:5000'],
            'tone'         => ['required', 'in:formal,friendly,casual'],
            'language'     => ['required', 'string', 'max:50'],
            'business_phone' => ['nullable', 'string', 'max:50'],
            'faqs'         => ['nullable', 'array'],
            'faqs.*.q'     => ['required', 'string', 'max:500'],
            'faqs.*.a'     => ['required', 'string', 'max:2000'],
        ]);

        $business = $request->user()->business;

        $business->ai_config = array_merge($business->ai_config ?? [], $validated);
        $business->save();

        return redirect()->route('settings.ai')->with('success', 'AI training content saved.');
    }

    /**
     * Return the compiled system prompt for the live preview panel.
     *
     * This endpoint is called by Alpine.js whenever any field changes in the
     * AI settings form, building a real-time preview from the submitted values
     * (not yet persisted to DB) so the owner can review before saving.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request): \Illuminate\Http\Response
    {
        $business  = $request->user()->business;
        $aiConfig  = $this->normalisePreviewConfig($request, $business->ai_config ?? []);

        $prompt = $this->compilePrompt($business, $aiConfig);

        return response($prompt, 200, ['Content-Type' => 'text/plain']);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Compile the full system prompt from business data and ai_config fields.
     *
     * @param  \App\Models\Business  $business
     * @param  array<string, mixed>  $aiConfig
     * @return string
     */
    private function compilePrompt(\App\Models\Business $business, array $aiConfig): string
    {
        $aiName      = $aiConfig['ai_name']  ?? 'AI Receptionist';
        $persona     = $aiConfig['persona']  ?? '';
        $tone        = $aiConfig['tone']     ?? 'friendly';
        $language    = $aiConfig['language'] ?? 'English';
        $services    = $this->businessServiceCatalog->promptCatalog($business);
        $faqs        = $aiConfig['faqs']     ?? [];

        $serviceLines = array_map(
            fn ($s) => "  - {$s['name']} ({$s['duration_min']} min)"
                . (isset($s['price']) ? " — {$s['price']}" : ''),
            $services,
        );

        $faqLines = array_map(
            fn ($f) => "  Q: {$f['q']}\n  A: {$f['a']}",
            $faqs,
        );

        return implode("\n\n", array_filter([
            "You are {$aiName}, a professional AI receptionist for {$business->name}, a {$business->business_type}.",
            $persona,
            ($aiConfig['business_phone'] ?? '') !== '' ? "BUSINESS PHONE: {$aiConfig['business_phone']}" : '',
            "SERVICES OFFERED:\n" . ($serviceLines ? implode("\n", $serviceLines) : '  (none configured)'),
            $faqs ? "FREQUENTLY ASKED QUESTIONS:\n" . implode("\n\n", $faqLines) : '',
            "LANGUAGE: Always respond in {$language}.",
            "TONE: {$tone}.",
        ]));
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function normalisePreviewConfig(Request $request, array $fallback): array
    {
        $payload = $request->input('ai_config');

        if (is_string($payload) && $payload !== '') {
            /** @var mixed $decoded */
            $decoded = json_decode($payload, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($payload)) {
            return $payload;
        }

        $data = $request->all();
        unset($data['_token']);

        return array_merge($fallback, array_filter($data, static fn (mixed $value): bool => $value !== null));
    }

    private function managedByAdmin(Request $request): bool
    {
        return $request->session()->has('impersonating_as') || $request->user()?->role === 'super_admin';
    }
}
