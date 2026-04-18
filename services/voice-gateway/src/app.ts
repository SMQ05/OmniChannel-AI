import Fastify from "fastify";
import websocket from "@fastify/websocket";
import * as Sentry from "@sentry/node";
import { gatewayConfig } from "./config.js";
import { logger } from "./logger.js";
import { healthRoutes } from "./routes/health.js";
import { telnyxMediaRoutes } from "./routes/telnyx-media.js";

export async function buildApp() {
  if (gatewayConfig.SENTRY_DSN) {
    Sentry.init({
      dsn: gatewayConfig.SENTRY_DSN
    });
  }

  const app = Fastify({
    logger
  });

  await app.register(websocket);
  await app.register(healthRoutes);
  await app.register(telnyxMediaRoutes);

  return app;
}
