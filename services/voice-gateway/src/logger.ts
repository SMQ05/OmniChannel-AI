import pino from "pino";
import { gatewayConfig } from "./config.js";

export const logger = pino({
  level: gatewayConfig.LOG_LEVEL,
  redact: {
    paths: ["req.headers.authorization", "headers.authorization", "*.apiKey", "*.sharedSecret"],
    censor: "[redacted]"
  }
});
