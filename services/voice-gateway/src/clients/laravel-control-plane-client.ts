import { gatewayConfig } from "../config.js";
import type { LaravelStartSessionResponse, OpenAiToolDefinition } from "../types.js";

type JsonValue = Record<string, unknown>;

export class LaravelControlPlaneClient {
  private readonly baseUrl = gatewayConfig.LARAVEL_INTERNAL_BASE_URL.replace(/\/$/, "");

  private async request<T>(path: string, init: RequestInit): Promise<T> {
    const response = await fetch(`${this.baseUrl}${path}`, {
      ...init,
      headers: {
        "Content-Type": "application/json",
        "X-Voice-Gateway-Secret": gatewayConfig.LARAVEL_INTERNAL_SECRET,
        ...(init.headers ?? {})
      }
    });

    if (!response.ok) {
      throw new Error(`Laravel control plane request failed: ${response.status} ${await response.text()}`);
    }

    return (await response.json()) as T;
  }

  fetchToolCatalog(): Promise<{ tools: OpenAiToolDefinition[] }> {
    return this.request("/api/internal/voice/tools", { method: "GET" });
  }

  startSession(payload: JsonValue): Promise<LaravelStartSessionResponse> {
    return this.request("/api/internal/voice/sessions/start", {
      method: "POST",
      body: JSON.stringify(payload)
    });
  }

  recordEvent(voiceSessionId: number, payload: JsonValue): Promise<{ id: number }> {
    return this.request(`/api/internal/voice/sessions/${voiceSessionId}/events`, {
      method: "POST",
      body: JSON.stringify(payload)
    });
  }

  recordTurn(voiceSessionId: number, payload: JsonValue): Promise<{ id: number }> {
    return this.request(`/api/internal/voice/sessions/${voiceSessionId}/turns`, {
      method: "POST",
      body: JSON.stringify(payload)
    });
  }

  recordUsage(voiceSessionId: number, payload: JsonValue): Promise<{ id: number }> {
    return this.request(`/api/internal/voice/sessions/${voiceSessionId}/usage`, {
      method: "POST",
      body: JSON.stringify(payload)
    });
  }

  storeSummary(voiceSessionId: number, payload: JsonValue): Promise<{ id: number }> {
    return this.request(`/api/internal/voice/sessions/${voiceSessionId}/summary`, {
      method: "POST",
      body: JSON.stringify(payload)
    });
  }

  completeSession(voiceSessionId: number, payload: JsonValue): Promise<{ id: number; status: string; summary_id?: number }> {
    return this.request(`/api/internal/voice/sessions/${voiceSessionId}/complete`, {
      method: "POST",
      body: JSON.stringify(payload)
    });
  }

  executeTool(tool: string, voiceSessionId: number, payload: JsonValue, idempotencyKey?: string): Promise<{ result: JsonValue; replayed: boolean }> {
    return this.request(`/api/internal/voice/tools/${tool}`, {
      method: "POST",
      headers: idempotencyKey ? { "X-Idempotency-Key": idempotencyKey } : {},
      body: JSON.stringify({
        voice_session_id: voiceSessionId,
        payload
      })
    });
  }
}
