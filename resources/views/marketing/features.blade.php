<x-layouts.marketing title="Features - Kynex AI Booking">
    <section class="mx-auto max-w-7xl px-6 py-20 lg:px-8">
        <div class="max-w-3xl">
            <div class="text-sm font-semibold uppercase tracking-[0.24em] text-indigo-200">Features</div>
            <h1 class="mt-4 text-5xl font-semibold tracking-tight text-white lg:text-6xl">Everything you need to run an AI front desk that saves time and captures more bookings.</h1>
            <p class="mt-6 text-lg leading-8 text-slate-300">
                Kynex AI Booking brings conversation handling, booking automation, reminders, voice-readiness, diagnostics, and admin control into one SaaS platform built for clinics and service businesses.
            </p>
        </div>

        <div class="mt-12 grid gap-5 lg:grid-cols-2">
            @foreach ([
                ['title' => 'AI Receptionist', 'items' => ['Handles inbound questions instantly', 'Responds even after hours', 'Keeps a consistent clinic tone and knowledge base']],
                ['title' => 'Booking Automation', 'items' => ['Books appointments automatically', 'Handles cancellations and reschedules', 'Uses provider and schedule logic']],
                ['title' => 'Conversation Inbox', 'items' => ['Centralized thread history', 'Channel visibility', 'Human handoff and follow-up context']],
                ['title' => 'Reminder Engine', 'items' => ['Automatic reminders', 'Configurable reminder timing', 'Supports follow-ups and reschedule prompts']],
                ['title' => 'Multi-Channel Front Desk', 'items' => ['WhatsApp', 'Messenger', 'Voice', 'SMS and web chat expansion path']],
                ['title' => 'Voice-Ready Architecture', 'items' => ['Shared business brain across text and voice', 'Provider-based voice stack', 'Call logs and rollout controls']],
                ['title' => 'Google Sync', 'items' => ['Google Calendar integration', 'Google Sheets integration', 'OAuth-based connection and diagnostics']],
                ['title' => 'Human Fallback', 'items' => ['Transfer to staff', 'Callback capture', 'Low-confidence escalation and urgency routing']],
                ['title' => 'Diagnostics And Admin Control', 'items' => ['Queue and worker visibility', 'Replay and retry tools', 'Usage tracking and rollout control']],
                ['title' => 'Built To Scale', 'items' => ['Multi-tenant SaaS architecture', 'Usage metering', 'Plan controls and admin oversight']],
            ] as $feature)
                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-8">
                    <h2 class="text-2xl font-semibold text-white">{{ $feature['title'] }}</h2>
                    <div class="mt-5 grid gap-3 text-sm text-slate-300">
                        @foreach ($feature['items'] as $item)
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2.5 w-2.5 rounded-full bg-cyan-400"></span>
                                <span>{{ $item }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-y border-white/10 bg-slate-950/50">
        <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-2">
                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-8">
                    <div class="text-sm font-semibold uppercase tracking-[0.24em] text-indigo-200">Trust and compliance</div>
                    <div class="mt-5 grid gap-3 text-sm text-slate-300">
                        <div>Business-level data isolation</div>
                        <div>Audit visibility for inbound and outbound activity</div>
                        <div>Role-based access</div>
                        <div>Webhook signature validation</div>
                        <div>Credential protection</div>
                        <div>Backup and recovery support at deployment level</div>
                        <div>Impersonation safeguards</div>
                        <div>Retention, export, and deletion policy support</div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-8">
                    <div class="text-sm font-semibold uppercase tracking-[0.24em] text-indigo-200">Channel roadmap</div>
                    <div class="mt-5 grid gap-3 text-sm text-slate-300">
                        <div>WhatsApp</div>
                        <div>Messenger</div>
                        <div>Voice</div>
                        <div>SMS via Twilio</div>
                        <div>Website chat widget</div>
                        <div>CRM and webhook integrations</div>
                        <div>Email alerts</div>
                        <div>EHR/EMR integrations</div>
                        <div>Payment links</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
        <div class="rounded-[2rem] border border-indigo-400/20 bg-gradient-to-br from-indigo-500/15 via-slate-950 to-cyan-400/10 p-10 text-center">
            <h2 class="text-4xl font-semibold text-white">See how Kynex AI Booking fits your front desk operation.</h2>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-300">
                Bring bookings, reminders, voice readiness, analytics, and admin control into one system built to reduce repetitive front-desk work.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="{{ route('marketing.pricing') }}" class="rounded-full bg-indigo-500 px-6 py-3 text-sm font-semibold text-white hover:bg-indigo-400">View Pricing</a>
                <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="rounded-full border border-white/15 px-6 py-3 text-sm font-semibold text-slate-200 hover:border-indigo-400/50 hover:text-white">Book a Demo</a>
            </div>
        </div>
    </section>
</x-layouts.marketing>
