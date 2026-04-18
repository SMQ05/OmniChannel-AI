import type { FastifyPluginAsync } from "fastify";
import { LaravelControlPlaneClient } from "../clients/laravel-control-plane-client.js";
import { SessionManager } from "../session/session-manager.js";

export const telnyxMediaRoutes: FastifyPluginAsync = async (fastify) => {
  const laravelClient = new LaravelControlPlaneClient();
  const sessionManager = new SessionManager(laravelClient);

  fastify.get("/ws/telnyx-media", { websocket: true }, (socket) => {
    sessionManager.attach(socket);
  });
};
