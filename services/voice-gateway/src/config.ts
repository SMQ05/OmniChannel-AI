import { z } from "zod";

const schema = z.object({
  PORT: z.coerce.number().default(3001),
  HOST: z.string().default("0.0.0.0"),
  LOG_LEVEL: z.string().default("info"),
  SENTRY_DSN: z.string().optional(),
  REDIS_URL: z.string().default("redis://redis:6379/0"),
  LARAVEL_INTERNAL_BASE_URL: z.string().default("http://nginx"),
  LARAVEL_INTERNAL_SECRET: z.string().min(1),
  OPENAI_API_KEY: z.string().min(1),
  OPENAI_REALTIME_BASE_URL: z.string().default("wss://api.openai.com/v1/realtime"),
  VOICE_GATEWAY_PRIMARY_MODEL: z.string().default("gpt-realtime"),
  VOICE_GATEWAY_FALLBACK_MODEL: z.string().default("gpt-realtime-mini"),
  VOICE_GATEWAY_OPENAI_VOICE: z.string().default("alloy"),
  VOICE_GATEWAY_TEMPERATURE: z.coerce.number().default(0.6),
  VOICE_GATEWAY_MAX_OUTPUT_TOKENS: z.coerce.number().default(700),
  VOICE_GATEWAY_SILENCE_TIMEOUT_MS: z.coerce.number().default(6000),
  VOICE_GATEWAY_REPROMPT_LIMIT: z.coerce.number().default(2),
  VOICE_GATEWAY_SHADOW_DEEPGRAM: z.coerce.boolean().default(true),
  DEEPGRAM_API_KEY: z.string().optional()
});

export type GatewayConfig = z.infer<typeof schema>;

export const gatewayConfig: GatewayConfig = schema.parse(process.env);
