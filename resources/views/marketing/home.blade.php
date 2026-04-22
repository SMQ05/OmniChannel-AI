<x-layouts.marketing title="Kynex AI Booking">
    <section class="mx-auto max-w-7xl px-6 py-20 lg:px-8 lg:py-28">
        <div class="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]">
            <div>
                <div class="inline-flex rounded-full border border-indigo-400/20 bg-indigo-500/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">
                    AI Front Desk Platform
                </div>
                <h1 class="mt-6 max-w-4xl text-5xl font-semibold tracking-tight text-[var(--text-strong)] lg:text-7xl">
                    AI front desk automation that captures more bookings and reduces front-desk workload.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-[var(--text-muted)]">
                    Kynex AI Booking helps clinics respond 24/7, answer FAQs instantly, automate booking, cancellation, and rescheduling, and keep one shared knowledge brain across WhatsApp, Messenger, voice, and future channel expansion.
                </p>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">
                        Book a Demo
                    </a>
                    <a href="{{ route('marketing.features') }}" class="btn-secondary rounded-full px-6 py-3 text-sm font-semibold">
                        See Features
                    </a>
                </div>

                <div class="mt-4 text-sm text-[var(--text-muted)]">
                    Setup, rollout, and training by
                    <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="font-medium text-[var(--brand)] hover:text-[var(--text-strong)]">
                        Kynex Solutions (kynexsolutions.com)
                    </a>
                </div>

                <div class="mt-10 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'available 24/7',
                        'never sleeps',
                        'responds even after hours',
                        'captures inquiries while staff are busy',
                        'reduces missed calls and missed bookings',
                        'automates booking, cancellation, and rescheduling',
                        'answers common questions instantly',
                        'works across WhatsApp, Messenger, and voice',
                        'keeps one shared knowledge brain across all channels',
                        'helps staff focus on patients instead of repetitive calls',
                        'built for scale with usage tracking, diagnostics, and admin control',
                    ] as $highlight)
                        <div class="flex items-start gap-3 rounded-2xl border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 px-4 py-3 text-sm text-[var(--text-base)]">
                            <span class="mt-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                            <span>{{ $highlight }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-[2rem] border border-[var(--border-subtle)] bg-[var(--surface-2)]/92 p-6 shadow-[var(--shadow-card)]">
                <div class="grid gap-4">
                    <div class="rounded-3xl border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 p-5">
                        <div class="text-xs uppercase tracking-[0.24em] text-slate-500">What clinics get</div>
                        <div class="mt-3 grid gap-3 text-sm text-[var(--text-base)]">
                            <div>Fewer missed bookings</div>
                            <div>Faster replies after hours</div>
                            <div>Fewer repetitive receptionist tasks</div>
                            <div>Centralized booking conversations</div>
                            <div>Automatic reminders and reschedules</div>
                            <div>Better visibility into demand and missed leads</div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-[var(--border-subtle)] bg-gradient-to-br from-indigo-500/15 to-cyan-400/10 p-5">
                        <div class="text-xs uppercase tracking-[0.24em] text-[var(--text-muted)]">One shared business brain</div>
                        <p class="mt-3 text-sm leading-7 text-[var(--text-base)]">
                            One knowledge layer, one booking orchestration layer, and multiple customer-facing channels including WhatsApp, Messenger, voice, future messaging expansion, CRM/webhooks, email alerts, EHR/EMR integrations, and payment links.
                        </p>
                    </div>

                    <div class="rounded-3xl border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 p-5">
                        <div class="text-xs uppercase tracking-[0.24em] text-slate-500">Built for trust and control</div>
                        <p class="mt-3 text-sm leading-7 text-[var(--text-muted)]">
                            Data isolation, audit visibility, role-based access, webhook signature validation, diagnostics, retry tools, usage tracking, and admin-managed setup all stay in the loop while automation handles repetitive front-desk work.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-[var(--border-subtle)] bg-[var(--surface-2)]/90">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="max-w-3xl">
                <div class="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">What it is</div>
                <h2 class="mt-4 text-3xl font-semibold text-[var(--text-strong)] lg:text-4xl">More than a chatbot. Built like a real front desk operations layer.</h2>
                <p class="mt-4 text-lg text-[var(--text-muted)]">
                    Kynex AI Booking is an AI receptionist, booking automation system, conversation inbox, reminder engine, and voice-ready front desk layer for clinics and service businesses.
                </p>
            </div>

            <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ([
                    ['title' => 'AI receptionist', 'copy' => 'Answers common questions instantly and stays responsive after hours.'],
                    ['title' => 'Booking automation', 'copy' => 'Books, cancels, and reschedules appointments without repetitive staff handling.'],
                    ['title' => 'Conversation inbox', 'copy' => 'Keeps booking conversations visible across channels in one operating layer.'],
                    ['title' => 'Reminder engine', 'copy' => 'Sends reminders and helps reduce no-shows and scheduling friction.'],
                    ['title' => 'Voice-ready front desk', 'copy' => 'Brings phone handling into the same shared booking brain as text channels.'],
                    ['title' => 'Usage-metered SaaS', 'copy' => 'Gives operators diagnostics, rollout controls, and billing visibility as volume scales.'],
                ] as $item)
                    <div class="rounded-3xl border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 p-6">
                        <h3 class="text-xl font-semibold text-[var(--text-strong)]">{{ $item['title'] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-[var(--text-muted)]">{{ $item['copy'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-2">
            <div class="rounded-[2rem] border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 p-8">
                <div class="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">Human fallback</div>
                <h2 class="mt-4 text-3xl font-semibold text-[var(--text-strong)]">Automation supports staff. It does not remove them from the loop.</h2>
                <div class="mt-6 grid gap-3 text-sm text-[var(--text-muted)]">
                    <div>Transfer to staff when needed</div>
                    <div>Capture callback requests</div>
                    <div>Recover missed calls and missed conversations</div>
                    <div>Escalate when AI confidence is low</div>
                    <div>Apply handoff rules by time, department, or urgency</div>
                </div>
            </div>

            <div class="rounded-[2rem] border border-[var(--border-subtle)] bg-[var(--surface-1)]/82 p-8">
                <div class="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">Analytics</div>
                <h2 class="mt-4 text-3xl font-semibold text-[var(--text-strong)]">Business visibility that shows what automation is really doing.</h2>
                <div class="mt-6 grid gap-3 text-sm text-[var(--text-muted)]">
                    <div>Appointments booked by AI</div>
                    <div>Cancellations and reschedules handled automatically</div>
                    <div>Response time across channels</div>
                    <div>Reminders sent and recovered conversations</div>
                    <div>Voice minutes used and FAQ trends</div>
                    <div>Channel volume and missed lead patterns</div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-t border-[var(--border-subtle)] bg-[var(--surface-2)]/92">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="rounded-[2rem] border border-indigo-400/20 bg-gradient-to-br from-indigo-500/15 via-white/80 to-cyan-400/10 p-10 text-center dark:via-slate-950">
                <div class="text-sm font-semibold uppercase tracking-[0.24em] text-[var(--brand)]">Ready to replace repetitive front-desk work?</div>
                <h2 class="mt-4 text-4xl font-semibold text-[var(--text-strong)]">Capture more bookings, reduce missed calls, and stay responsive 24/7.</h2>
                <p class="mx-auto mt-4 max-w-3xl text-lg text-[var(--text-muted)]">
                    Kynex AI Booking gives clinics an AI front desk that never sleeps, keeps one shared knowledge brain across channels, and gives your team more time to focus on patients instead of repetitive calls.
                </p>
                <div class="mt-8 flex flex-wrap justify-center gap-4">
                    <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="btn-primary rounded-full px-6 py-3 text-sm font-semibold">
                        Book a Demo
                    </a>
                    <a href="{{ route('marketing.pricing') }}" class="btn-secondary rounded-full px-6 py-3 text-sm font-semibold">
                        View Pricing
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.marketing>
