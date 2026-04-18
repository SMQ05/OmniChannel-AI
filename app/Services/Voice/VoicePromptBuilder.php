<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Models\Business;
use App\Models\VoiceChannel;

class VoicePromptBuilder
{
    /**
     * @param  array<string, mixed>  $dynamicContext
     * @return array{
     *     global: string,
     *     tenant: string,
     *     policy: string,
     *     dynamic: string,
     *     combined: string
     * }
     */
    public function build(Business $business, ?VoiceChannel $voiceChannel = null, array $dynamicContext = []): array
    {
        $tenantPrompt = $this->tenantPrompt($business, $voiceChannel);
        $dynamicPrompt = $this->dynamicPrompt($dynamicContext);

        return [
            'global' => (string) config('voice_gateway.prompts.global'),
            'tenant' => $tenantPrompt,
            'policy' => (string) config('voice_gateway.prompts.policy'),
            'dynamic' => $dynamicPrompt,
            'combined' => implode("\n\n", array_filter([
                (string) config('voice_gateway.prompts.global'),
                $tenantPrompt,
                (string) config('voice_gateway.prompts.policy'),
                $dynamicPrompt,
            ])),
        ];
    }

    private function tenantPrompt(Business $business, ?VoiceChannel $voiceChannel): string
    {
        $aiConfig = $business->ai_config ?? [];
        $services = $aiConfig['services'] ?? [];
        $faqs = $aiConfig['faqs'] ?? [];
        $voiceConfig = $business->channel_config['voice'] ?? [];

        $serviceLines = array_map(
            static fn (array $service): string => sprintf(
                '- %s (%d min)%s',
                (string) ($service['name'] ?? 'Service'),
                (int) ($service['duration_min'] ?? 30),
                isset($service['price']) && $service['price'] !== null ? sprintf(' — %s', (string) $service['price']) : '',
            ),
            is_array($services) ? $services : [],
        );

        $faqLines = array_map(
            static fn (array $faq): string => sprintf(
                'Q: %s' . "\n" . 'A: %s',
                (string) ($faq['q'] ?? ''),
                (string) ($faq['a'] ?? ''),
            ),
            is_array($faqs) ? $faqs : [],
        );

        return implode("\n\n", array_filter([
            sprintf('Business: %s (%s).', $business->name, $business->business_type),
            sprintf('AI name: %s.', (string) ($aiConfig['ai_name'] ?? 'Kynex Voice')),
            sprintf('Tone: %s. Language: %s.', (string) ($aiConfig['tone'] ?? 'friendly'), (string) ($aiConfig['language'] ?? 'English')),
            $voiceChannel !== null ? sprintf('Inbound voice channel: %s via %s.', (string) ($voiceChannel->phone_number ?? 'unknown'), $voiceChannel->provider) : null,
            !empty($voiceConfig['greeting']) ? 'Greeting guidance: ' . (string) $voiceConfig['greeting'] : null,
            !empty($voiceConfig['handoff_message']) ? 'Handoff guidance: ' . (string) $voiceConfig['handoff_message'] : null,
            $serviceLines !== [] ? "Services:\n" . implode("\n", $serviceLines) : null,
            $faqLines !== [] ? "FAQs:\n" . implode("\n\n", $faqLines) : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $dynamicContext
     */
    private function dynamicPrompt(array $dynamicContext): string
    {
        $lines = [];

        foreach ($dynamicContext as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $lines[] = sprintf('%s: %s', $key, (string) $value);
            }
        }

        return $lines === [] ? '' : "Dynamic session context:\n" . implode("\n", $lines);
    }
}
