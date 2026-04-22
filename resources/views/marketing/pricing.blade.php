<x-layouts.marketing title="Pricing - Kynex AI Booking">
    <section class="mx-auto max-w-7xl px-6 py-20 lg:px-8">
        <div class="max-w-3xl">
            <div class="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">Pricing</div>
            <h1 class="mt-4 text-5xl font-semibold tracking-tight text-[var(--text-strong)] lg:text-6xl">Pricing built around setup, automation, and scale.</h1>
            <p class="mt-6 text-lg leading-8 text-[var(--text-muted)]">
                Kynex AI Booking is delivered as a managed AI front desk platform. Kynex handles setup, training, channel configuration, integrations, reminders, and voice rollout so clinics get operational value without carrying the implementation burden themselves.
            </p>
        </div>

        <div class="mt-12 grid gap-6 xl:grid-cols-3">
            @foreach ([
                [
                    'name' => 'Launch',
                    'price' => '$69/mo',
                    'tagline' => 'For solo clinics and small teams.',
                    'items' => ['120 voice minutes', 'AI receptionist with booking, cancellation, and rescheduling', 'Google Calendar sync', 'Google Sheets sync', 'Messenger', 'WhatsApp enabled', 'Reminders', '2 staff users', 'Basic analytics and diagnostics', 'LLM included', '$10 monthly messaging credit'],
                    'overages' => ['Voice: $0.14/min', 'Messaging: pay-as-you-go after the $10 credit'],
                ],
                [
                    'name' => 'Growth',
                    'price' => '$119/mo',
                    'tagline' => 'For active clinics.',
                    'items' => ['Everything in Launch', '300 voice minutes', '5 staff users', 'After-hours logic', 'Transfer and escalation rules', 'Richer clinic knowledge base', 'Priority support', '$25 monthly messaging credit'],
                    'overages' => ['Voice: $0.12/min', 'Messaging: pay-as-you-go after the $25 credit'],
                ],
                [
                    'name' => 'Pro',
                    'price' => '$219/mo',
                    'tagline' => 'For busy clinics or multi-provider setups.',
                    'items' => ['Everything in Growth', '800 voice minutes', '15 staff users', 'Multi-calendar and multi-provider handling', 'Advanced reporting', 'Custom intake logic', 'Custom prompt tuning', 'Priority rollout support', '$60 monthly messaging credit'],
                    'overages' => ['Voice: $0.10/min', 'Messaging: pay-as-you-go after the $60 credit'],
                ],
            ] as $card)
                <div class="rounded-[2rem] border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 p-8">
                    <div class="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">{{ $card['name'] }}</div>
                    <div class="mt-3 text-4xl font-semibold text-[var(--text-strong)]">{{ $card['price'] }}</div>
                    <p class="mt-4 text-sm leading-7 text-[var(--text-muted)]">{{ $card['tagline'] }}</p>
                    <div class="mt-6 grid gap-3 text-sm text-[var(--text-base)]">
                        @foreach ($card['items'] as $item)
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                <span>{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-2)]/92 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.24em] text-[var(--text-muted)]">Overages</div>
                        <div class="mt-3 grid gap-2 text-sm text-[var(--text-muted)]">
                            @foreach ($card['overages'] as $overage)
                                <div>{{ $overage }}</div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-8">
                        <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="btn-primary rounded-full px-5 py-3 text-sm font-semibold">
                            Talk to Sales
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-y border-[var(--border-subtle)] bg-[var(--surface-2)]/90">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-2">
                <div class="rounded-[2rem] border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 p-8">
                    <div class="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">Pricing notes</div>
                    <div class="mt-5 grid gap-3 text-sm text-[var(--text-muted)]">
                        <div>One-time setup and training fee</div>
                        <div>Recurring platform fee</div>
                        <div>Voice and messaging overages scale with actual usage</div>
                        <div>Monthly messaging credit protects margin across US, UK, AU, and EU destinations</div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 p-8">
                    <div class="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">What the messaging credit means</div>
                    <div class="mt-5 grid gap-3 text-sm text-[var(--text-muted)]">
                        <div>A $10 credit can cover about 2,000 Twilio WhatsApp transport messages at roughly $0.005/message, excluding Meta template charges.</div>
                        <div>It can also be allocated toward future messaging expansion as those channels are enabled for your rollout.</div>
                        <div>That is why Kynex uses credit-based messaging instead of a flat message count that breaks across countries.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
        <div class="rounded-[2rem] border border-indigo-400/20 bg-gradient-to-br from-indigo-500/15 via-white/80 to-cyan-400/10 p-10 text-center dark:via-slate-950">
            <h2 class="text-4xl font-semibold text-[var(--text-strong)]">Choose the rollout that fits your clinic now and scales with you later.</h2>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-[var(--text-muted)]">
                Start with AI booking and reminders, then expand into voice, future messaging channels, integrations, and custom workflows as your volume grows.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">Talk to Sales</a>
                <a href="{{ route('marketing.features') }}" class="btn-secondary rounded-full px-6 py-3 text-sm font-semibold">See Features</a>
            </div>
        </div>
    </section>
</x-layouts.marketing>
