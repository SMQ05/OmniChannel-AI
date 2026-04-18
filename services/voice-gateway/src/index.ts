import { buildApp } from "./app.js";
import { gatewayConfig } from "./config.js";
import { logger } from "./logger.js";

const app = await buildApp();

try {
  await app.listen({
    port: gatewayConfig.PORT,
    host: gatewayConfig.HOST
  });

  logger.info({ port: gatewayConfig.PORT }, "Voice gateway listening.");
} catch (error) {
  logger.error({ err: error }, "Voice gateway failed to start.");
  process.exit(1);
}
