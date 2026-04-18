import { EventEmitter } from "node:events";
import WebSocket from "ws";
import { gatewayConfig } from "../config.js";
import type { OpenAiRealtimeEvent, OpenAiToolDefinition } from "../types.js";

type SessionSeed = {
  prompt: string;
  tools: OpenAiToolDefinition[];
  model?: string;
};

export class OpenAiRealtimeClient extends EventEmitter {
  private socket?: WebSocket;

  async connect(seed: SessionSeed): Promise<void> {
    const model = seed.model ?? gatewayConfig.VOICE_GATEWAY_PRIMARY_MODEL;
    const url = `${gatewayConfig.OPENAI_REALTIME_BASE_URL}?model=${encodeURIComponent(model)}`;

    this.socket = new WebSocket(url, {
      headers: {
        Authorization: `Bearer ${gatewayConfig.OPENAI_API_KEY}`,
        "OpenAI-Beta": "realtime=v1"
      }
    });

    await new Promise<void>((resolve, reject) => {
      this.socket?.once("open", () => resolve());
      this.socket?.once("error", reject);
    });

    this.socket.on("message", (raw: WebSocket.RawData) => {
      const payload = JSON.parse(String(raw)) as OpenAiRealtimeEvent;
      this.emit("event", payload);
      this.emit(payload.type, payload);
    });

    this.socket.on("close", () => this.emit("close"));
    this.socket.on("error", (error) => this.emit("error", error));

    this.send({
      type: "session.update",
      session: {
        modalities: ["audio", "text"],
        voice: gatewayConfig.VOICE_GATEWAY_OPENAI_VOICE,
        instructions: seed.prompt,
        temperature: gatewayConfig.VOICE_GATEWAY_TEMPERATURE,
        max_response_output_tokens: gatewayConfig.VOICE_GATEWAY_MAX_OUTPUT_TOKENS,
        input_audio_format: "g711_ulaw",
        output_audio_format: "g711_ulaw",
        turn_detection: {
          type: "server_vad",
          silence_duration_ms: gatewayConfig.VOICE_GATEWAY_SILENCE_TIMEOUT_MS
        },
        tools: seed.tools
      }
    });
  }

  appendAudio(base64Audio: string): void {
    this.send({
      type: "input_audio_buffer.append",
      audio: base64Audio
    });
  }

  commitAudio(): void {
    this.send({ type: "input_audio_buffer.commit" });
  }

  requestResponse(): void {
    this.send({ type: "response.create" });
  }

  sendFunctionOutput(callId: string, output: unknown): void {
    this.send({
      type: "conversation.item.create",
      item: {
        type: "function_call_output",
        call_id: callId,
        output: JSON.stringify(output)
      }
    });

    this.requestResponse();
  }

  clearResponse(): void {
    this.send({ type: "response.cancel" });
  }

  close(code?: number): void {
    this.socket?.close(code);
  }

  private send(payload: Record<string, unknown>): void {
    if (!this.socket || this.socket.readyState !== WebSocket.OPEN) {
      throw new Error("OpenAI realtime socket is not connected.");
    }

    this.socket.send(JSON.stringify(payload));
  }
}
