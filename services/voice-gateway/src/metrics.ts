import { Counter, Histogram, Registry, collectDefaultMetrics } from "prom-client";

export const metricsRegistry = new Registry();
collectDefaultMetrics({ register: metricsRegistry });

export const firstResponseLatency = new Histogram({
  name: "voice_first_response_latency_ms",
  help: "Time between call start and first audio response.",
  registers: [metricsRegistry],
  buckets: [150, 250, 400, 600, 800, 1200, 2000, 4000]
});

export const turnLatency = new Histogram({
  name: "voice_average_turn_latency_ms",
  help: "Latency per voice turn.",
  registers: [metricsRegistry],
  buckets: [100, 200, 300, 500, 800, 1200, 2000]
});

export const interruptions = new Counter({
  name: "voice_interruptions_total",
  help: "Number of caller interruptions/barge-ins.",
  registers: [metricsRegistry]
});

export const toolLatency = new Histogram({
  name: "voice_tool_latency_ms",
  help: "Latency for Laravel tool execution.",
  labelNames: ["tool"],
  registers: [metricsRegistry],
  buckets: [20, 50, 100, 200, 400, 800, 1500, 3000]
});

export const transferRate = new Counter({
  name: "voice_transfer_total",
  help: "Number of transfers requested.",
  registers: [metricsRegistry]
});

export const fallbackRate = new Counter({
  name: "voice_fallback_total",
  help: "Number of sessions that entered fallback mode.",
  labelNames: ["provider"],
  registers: [metricsRegistry]
});

export const providerErrors = new Counter({
  name: "voice_provider_errors_total",
  help: "Errors grouped by provider.",
  labelNames: ["provider"],
  registers: [metricsRegistry]
});

export const bookingSuccess = new Counter({
  name: "voice_booking_success_total",
  help: "Successful voice booking tool executions.",
  registers: [metricsRegistry]
});
