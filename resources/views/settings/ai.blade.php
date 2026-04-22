<x-layouts.app title="AI Training">

<div class="mx-auto max-w-6xl space-y-6">
    <div class="panel p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <div class="page-eyebrow">Business-Owned Training</div>
                <h2 class="mt-2 text-xl font-semibold text-white">AI training content belongs to the business team.</h2>
                <p class="mt-2 text-sm text-gray-400">Your team now owns assistant identity, tone, phone contact, and FAQs. Platform model routing still stays centralized, and the shared brain is still not visually or structurally centralized enough.</p>
            </div>
            <div class="rounded-xl border border-gray-800 px-4 py-3 text-sm text-gray-300">
                <div class="font-medium text-white">Runtime note</div>
                <div class="mt-1 text-gray-500">Structured services from the operations domain are now the primary service source. Legacy `ai_config.services` remains as fallback only.</div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('settings.ai.update') }}"
          x-data="{
              previewText: @js($compiledPrompt),
              previewError: '',
              async fetchPreview() {
                  const formData = new FormData(this.$refs.form);
                  const response = await fetch('{{ route('settings.ai.preview') }}', {
                      method: 'POST',
                      headers: {
                          'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                          'X-Requested-With': 'XMLHttpRequest',
                      },
                      body: formData,
                  });

                  const text = await response.text();
                  if (!response.ok) {
                      this.previewError = text || 'Preview unavailable right now.';
                      return;
                  }

                  this.previewError = '';
                  this.previewText = text;
              }
          }"
          x-ref="form"
          @input.debounce.250ms="fetchPreview()"
          @change="fetchPreview()">
        @csrf

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr,0.9fr]">
            <div class="space-y-5">
                <div class="panel p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-white">Assistant Identity</h2>

                    <div>
                        <label class="mb-1 block text-xs text-gray-500">AI name *</label>
                        <input type="text" name="ai_name" value="{{ old('ai_name', $aiConfig['ai_name'] ?? '') }}" required class="w-full field">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Business phone</label>
                        <input type="text" name="business_phone" value="{{ old('business_phone', $aiConfig['business_phone'] ?? '') }}" class="w-full field" placeholder="+1 555 0100">
                        <p class="mt-1 text-xs text-gray-600">Used in fallback replies and reminder templates when `{business_phone}` is present.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Persona instructions *</label>
                        <textarea name="persona" rows="6" required class="w-full field">{{ old('persona', $aiConfig['persona'] ?? '') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Tone</label>
                            <select name="tone" class="w-full field">
                                @foreach(['formal' => 'Formal', 'friendly' => 'Friendly', 'casual' => 'Casual'] as $val => $label)
                                    <option value="{{ $val }}" @selected(old('tone', $aiConfig['tone'] ?? 'friendly') === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">Language</label>
                            <select name="language" class="w-full field">
                                @foreach(['English','Urdu','Arabic','French','Spanish','Hindi','Other'] as $lang)
                                    <option value="{{ $lang }}" @selected(old('language', $aiConfig['language'] ?? 'English') === $lang)>{{ $lang }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="panel p-5"
                     x-data="{
                         faqs: @js(old('faqs', $aiConfig['faqs'] ?? [])),
                         add() { this.faqs.push({ q: '', a: '' }); },
                         remove(i) { this.faqs.splice(i, 1); }
                     }">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-white">FAQs</h2>
                        <button type="button" @click="add()" class="text-xs text-[var(--brand)] hover:text-[var(--brand-strong)]">+ Add FAQ</button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(faq, i) in faqs" :key="i">
                            <div class="space-y-2 rounded-xl border border-gray-800 p-4">
                                <div class="flex items-center gap-2">
                                    <input type="text" :name="'faqs[' + i + '][q]'" x-model="faq.q" placeholder="Question" class="w-full field">
                                    <button type="button" @click="remove(i)" class="text-gray-600 hover:text-red-400">✕</button>
                                </div>
                                <textarea :name="'faqs[' + i + '][a]'" x-model="faq.a" rows="3" placeholder="Answer" class="w-full field"></textarea>
                            </div>
                        </template>
                        <template x-if="faqs.length === 0">
                            <div class="rounded-xl border border-dashed border-gray-800 px-4 py-6 text-sm text-gray-500">No FAQs yet.</div>
                        </template>
                    </div>
                </div>

                <div class="panel p-5">
                    <div class="mb-3 flex items-center justify-between gap-4">
                        <h2 class="text-sm font-semibold text-white">Service Source</h2>
                        <a href="{{ route('services.index') }}" class="text-xs text-[var(--brand)] hover:text-[var(--brand-strong)]">Open services →</a>
                    </div>
                    @if($structuredServices->isNotEmpty())
                        <div class="space-y-3">
                            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                                Structured services are active. The booking brain will prefer these records before any legacy fallback.
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($structuredServices as $service)
                                    <span class="rounded-full border border-gray-700 px-3 py-1.5 text-xs text-gray-300">{{ $service->name }} · {{ $service->duration_minutes }}m</span>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                            No structured services yet. The system still relies on {{ $legacyServiceFallbackCount }} legacy fallback service definition{{ $legacyServiceFallbackCount === 1 ? '' : 's' }} from older AI config.
                        </div>
                    @endif
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-6 py-2.5">Save AI Training</button>
                </div>
            </div>

            <div class="space-y-6">
                <div class="panel overflow-hidden sticky top-6">
                    <div class="border-b border-gray-800 px-5 py-4">
                        <h2 class="text-sm font-semibold text-white">Live Prompt Preview</h2>
                        <p class="mt-1 text-xs text-gray-600">This uses your current training fields plus the live service catalog.</p>
                    </div>
                    <div x-show="previewError" class="mx-5 mt-4 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs text-red-300" x-text="previewError"></div>
                    <pre class="max-h-[70vh] overflow-auto whitespace-pre-wrap p-5 font-mono text-xs leading-relaxed text-gray-400" x-text="previewText"></pre>
                </div>

                <div class="panel p-5">
                    <h2 class="text-sm font-semibold text-white">Platform-Managed Runtime</h2>
                    <div class="mt-4 space-y-3 text-sm text-gray-300">
                        <div class="panel-subtle px-4 py-3">
                            <div class="font-medium text-white">LLM provider</div>
                            <div class="mt-1 text-gray-500">{{ strtoupper($aiConfig['llm_provider'] ?? 'claude') }} is still centrally managed for runtime stability.</div>
                        </div>
                        <div class="panel-subtle px-4 py-3">
                            <div class="font-medium text-white">Legacy fallback</div>
                            <div class="mt-1 text-gray-500">Legacy service fallback remains in place until structured services and `service_id` are fully proven across reminders, conversations, exports, voice, and syncs.</div>
                        </div>
                        <div class="panel-subtle px-4 py-3">
                            <div class="font-medium text-white">Architectural note</div>
                            <div class="mt-1 text-gray-500">Current architectural weakness: the shared brain is not visually or structurally centralized enough.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

</x-layouts.app>
