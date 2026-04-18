import type { FastifyPluginAsync } from "fastify";
import { metricsRegistry } from "../metrics.js";

export const healthRoutes: FastifyPluginAsync = async (fastify) => {
  fastify.get("/health", async () => ({
    ok: true
  }));

  fastify.get("/metrics", async (_request, reply) => {
    reply.header("Content-Type", metricsRegistry.contentType);
    return metricsRegistry.metrics();
  });
};
