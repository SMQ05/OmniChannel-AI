<x-layouts.app title="AI Training">

<div class="max-w-4xl mx-auto">

    <form method="POST" action="{{ route('settings.ai.update') }}"
          x-data="{
              aiConfig: @js($aiConfig),
              previewText: '',
              async fetchPreview() {
                  const res = await fetch('{{ route('settings.ai.preview') }}?ai_config=' + encodeURIComponent(JSON.stringify(this.aiConfig)), {
                      headers: { 'X-Requested-With': 'XMLHttpRequest' }
                  });
                  this.previewText = await res.text();
              }
          }"
          @change="fetchPreview()"
          x-init="fetchPreview()">
        @csrf

        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

            {{-- ============================================================
                 SETTINGS PANEL
                 ============================================================ --}}
            <div class="space-y-5">

                {{-- Identity --}}
                <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-white">AI Identity</h2>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">AI Name *</label>
                        <input type="text" name="ai_name"
                               value="{{ old('ai_name', $aiConfig['ai_name'] ?? '') }}"
                               x-model="aiConfig.ai_name"
                               required
                               placeholder="e.g. Sara, Alex, Zara"
                               class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                      focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Persona Instructions *</label>
                        <textarea name="persona" rows="5" required
                                  x-model="aiConfig.persona"
                                  placeholder="e.g. You are warm, patient, and professional. Greet returning patients by name…"
                                  class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                         focus:ring-indigo-500 focus:border-indigo-500">{{ old('persona', $aiConfig['persona'] ?? '') }}</textarea>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Tone</label>
                            <select name="tone" x-model="aiConfig.tone"
                                    class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
                                @foreach(['formal' => 'Formal', 'friendly' => 'Friendly', 'casual' => 'Casual'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('tone', $aiConfig['tone'] ?? 'friendly') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Language</label>
                            <select name="language" x-model="aiConfig.language"
                                    class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
                                @foreach(['English','Urdu','Arabic','French','Spanish','Hindi','Other'] as $lang)
                                    <option value="{{ $lang }}" {{ old('language', $aiConfig['language'] ?? 'English') === $lang ? 'selected' : '' }}>{{ $lang }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">LLM Provider</label>
                            <select name="llm_provider" x-model="aiConfig.llm_provider"
                                    class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
                                @foreach(['claude' => 'Claude', 'gpt4o' => 'GPT-4o', 'minimax' => 'MiniMax'] as $val => $label)
                                    <option value="{{ $val }}" {{ old('llm_provider', $aiConfig['llm_provider'] ?? 'claude') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Services repeater --}}
                <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5"
                     x-data="{
                         services: @js($aiConfig['services'] ?? []),
                         add() { this.services.push({ name: '', duration_min: 30, price: null }); },
                         remove(i) { this.services.splice(i, 1); }
                     }">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-white">Services</h2>
                        <button type="button" @click="add()"
                                class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors">+ Add service</button>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(service, i) in services" :key="i">
                            <div class="grid grid-cols-12 gap-2 items-center">
                                <input type="text" :name="'services[' + i + '][name]'"
                                       x-model="service.name" placeholder="Service name"
                                       class="col-span-5 bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-2 py-1.5
                                              focus:ring-indigo-500 focus:border-indigo-500">
                                <input type="number" :name="'services[' + i + '][duration_min]'"
                                       x-model="service.duration_min" placeholder="min" min="5"
                                       class="col-span-3 bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-2 py-1.5
                                              focus:ring-indigo-500 focus:border-indigo-500">
                                <input type="number" :name="'services[' + i + '][price]'"
                                       x-model="service.price" placeholder="Price" min="0" step="0.01"
                                       class="col-span-3 bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-2 py-1.5
                                              focus:ring-indigo-500 focus:border-indigo-500">
                                <button type="button" @click="remove(i)"
                                        class="col-span-1 text-gray-600 hover:text-red-400 transition-colors text-center">✕</button>
                            </div>
                        </template>
                        <template x-if="services.length === 0">
                            <p class="text-xs text-gray-600">No services added yet.</p>
                        </template>
                    </div>
                </div>

                {{-- FAQ repeater --}}
                <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5"
                     x-data="{
                         faqs: @js($aiConfig['faqs'] ?? []),
                         add() { this.faqs.push({ q: '', a: '' }); },
                         remove(i) { this.faqs.splice(i, 1); }
                     }">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-semibold text-white">FAQs</h2>
                        <button type="button" @click="add()"
                                class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors">+ Add FAQ</button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(faq, i) in faqs" :key="i">
                            <div class="space-y-1.5">
                                <div class="flex gap-2">
                                    <input type="text" :name="'faqs[' + i + '][q]'"
                                           x-model="faq.q" placeholder="Question"
                                           class="flex-1 bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-2 py-1.5
                                                  focus:ring-indigo-500 focus:border-indigo-500">
                                    <button type="button" @click="remove(i)"
                                            class="text-gray-600 hover:text-red-400 transition-colors">✕</button>
                                </div>
                                <textarea :name="'faqs[' + i + '][a]'"
                                          x-model="faq.a" placeholder="Answer" rows="2"
                                          class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-xs rounded-lg px-2 py-1.5
                                                 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                        </template>
                        <template x-if="faqs.length === 0">
                            <p class="text-xs text-gray-600">No FAQs added yet.</p>
                        </template>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                        Save AI Configuration
                    </button>
                </div>
            </div>

            {{-- ============================================================
                 LIVE PROMPT PREVIEW
                 ============================================================ --}}
            <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden sticky top-6 self-start">
                <div class="px-5 py-4 border-b border-gray-800">
                    <h2 class="text-sm font-semibold text-white">Live System Prompt Preview</h2>
                    <p class="text-xs text-gray-600 mt-0.5">This is exactly what your AI receives as training on every conversation.</p>
                </div>
                <pre class="p-5 text-xs text-gray-400 font-mono whitespace-pre-wrap overflow-auto max-h-[70vh] leading-relaxed"
                     x-text="previewText || 'Fill in the fields on the left to generate your AI\'s system prompt…'"></pre>
            </div>

        </div>
    </form>
</div>

</x-layouts.app>
