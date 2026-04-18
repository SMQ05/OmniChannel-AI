import type WebSocket from "ws";
import { logger } from "../logger.js";
import { LaravelControlPlaneClient } from "../clients/laravel-control-plane-client.js";
import { CallSession } from "./call-session.js";

export class SessionManager {
  private readonly sessions = new Map<string, CallSession>();

  constructor(private readonly laravelClient: LaravelControlPlaneClient) {}

  attach(socket: WebSocket): CallSession {
    const session = new CallSession(socket, this.laravelClient, () => this.release(session.id));
    this.sessions.set(session.id, session);
    return session;
  }

  release(id: string): void {
    this.sessions.delete(id);
    logger.info({ sessionId: id }, "Voice call session released.");
  }
}
