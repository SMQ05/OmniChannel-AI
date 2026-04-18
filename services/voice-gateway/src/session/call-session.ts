import { randomUUID } from "node:crypto";
import type WebSocket from "ws";
import { gatewayConfig } from "../config.js";
import { logger } from "../logger.js";
import { bookingSuccess, fallbackRate, firstResponseLatency, providerErrors, toolLatency, turnLatency } from "../metrics.js";
import { DeepgramShadowClient } from "../clients/deepgram-shadow-client.js";
import { LaravelControlPlaneClient } from "../clients/laravel-control-plane-client.js";
import { OpenAiRealtimeClient } from "../clients/openai-realtime-client.js";
import type { LaravelStartSessionResponse, OpenAiRealtimeEvent, TelnyxMessage } from "../types.js";

export class CallSession {
  readonly id = randomUUID();

  private readonly openAiClient = new OpenAiRealtimeClient();
  private readonly deepgramClient = new DeepgramShadowClient(
    gatewayConfig.VOICE_GATEWAY_SHADOW_DEEPGRAM ? gatewayConfig.DEEPGRAM_API_KEY : undefined
  );

  private controlPlaneSession?: LaravelStartSessionResponse["voice_session"];
  private callStartedAt = Date.now();
  private firstAudioResponseAt?: number;
  private repromptCount = 0;
  private callerNumber?: string;

  constructor(
    private readonly socket: WebSocket,
    private readonly laravelClient: LaravelControlPlaneClient,
    private readonly onClose: () => void
  ) {
    socket.on("message", (raw) => void this.handleRawMessage(raw.toString()));
    socket.on("close", () => void this.finalize("disconnected"));
    socket.on("error", (error) => {
      providerErrors.inc({ provider: "telnyx" });
      logger.error({ err: error, sessionId: this.id }, "Telnyx websocket error.");
    });
  }

  private async handleRawMessage(raw: string): Promise<void> {
    const message = JSON.parse(raw) as TelnyxMessage;

    switch (message.event) {
      case "start":
        await this.handleStart(message);
        break;
      case "media":
        await this.handleMedia(message);
        break;
      case "stop":
        await this.finalize("completed");
        break;
      default:
        break;
    }
  }

  private async handleStart(message: Extract<TelnyxMessage, { event: "start" }>): Promise<void> {
    const session = await this.laravelClient.startSession({
      provider: "telnyx",
      provider_call_id: message.start?.call_control_id ?? message.start?.call_leg_id ?? this.id,
      transport_stream_id: message.stream_id ?? null,
      direction: "inbound",
      from_number: message.start?.from ?? null,
      to_number: message.start?.to ?? null
    });

    this.controlPlaneSession = session.voice_session;
    this.callerNumber = message.start?.from ?? undefined;

    if (!session.accepted) {
      fallbackRate.inc({ provider: "openai_realtime" });
      await this.laravelClient.recordEvent(session.voice_session.id, {
        event_type: "session.rejected",
        source: "voice_gateway",
        severity: "warning",
        payload: { reason: session.reason }
      });

      this.socket.close();
      return;
    }

    await this.deepgramClient.connect();
    await this.openAiClient.connect({
      prompt: session.prompts.combined,
      tools: session.tools
    });

    this.bindOpenAiEvents();
    this.bindDeepgramEvents();
  }

  private async handleMedia(message: Extract<TelnyxMessage, { event: "media" }>): Promise<void> {
    const payload = message.media?.payload;

    if (!payload || !this.controlPlaneSession) {
      return;
    }

    this.openAiClient.appendAudio(payload);
    this.deepgramClient.sendMulawFrame(payload);
  }

  private bindOpenAiEvents(): void {
    this.openAiClient.on("response.audio.delta", async (event: OpenAiRealtimeEvent) => {
      const delta = String(event.delta ?? "");

      if (delta === "") {
        return;
      }

      if (!this.firstAudioResponseAt) {
        this.firstAudioResponseAt = Date.now();
        firstResponseLatency.observe(this.firstAudioResponseAt - this.callStartedAt);
      }

      this.socket.send(JSON.stringify({
        event: "media",
        media: { payload: delta }
      }));
    });

    this.openAiClient.on("conversation.item.input_audio_transcription.completed", async (event: OpenAiRealtimeEvent) => {
      if (!this.controlPlaneSession) {
        return;
      }

      const transcript = String(event.transcript ?? "");
      if (transcript === "") {
        return;
      }

      await this.laravelClient.recordTurn(this.controlPlaneSession.id, {
        role: "user",
        source: "openai_realtime",
        transcript
      });
    });

    this.openAiClient.on("response.output_text.done", async (event: OpenAiRealtimeEvent) => {
      if (!this.controlPlaneSession) {
        return;
      }

      const text = String(event.text ?? "");
      if (text === "") {
        return;
      }

      turnLatency.observe(Date.now() - this.callStartedAt);
      await this.laravelClient.recordTurn(this.controlPlaneSession.id, {
        role: "assistant",
        source: "openai_realtime",
        text
      });
    });

    this.openAiClient.on("response.function_call_arguments.done", async (event: OpenAiRealtimeEvent) => {
      if (!this.controlPlaneSession) {
        return;
      }

      const toolName = String(event.name ?? "");
      const callId = String(event.call_id ?? "");
      const rawArguments = String(event.arguments ?? "{}");
      const payload = JSON.parse(rawArguments) as Record<string, unknown>;
      const startedAt = Date.now();

      const toolResult = await this.laravelClient.executeTool(
        toolName,
        this.controlPlaneSession.id,
        payload,
        `${this.controlPlaneSession.uuid}:${toolName}:${callId}`
      );

      toolLatency.observe({ tool: toolName }, Date.now() - startedAt);

      if (toolName === "book_appointment" && toolResult.result.ok === true) {
        bookingSuccess.inc();
      }

      this.openAiClient.sendFunctionOutput(callId, toolResult.result);
    });

    this.openAiClient.on("error", async (error: Error) => {
      providerErrors.inc({ provider: "openai_realtime" });
      logger.error({ err: error, sessionId: this.id }, "OpenAI realtime error.");

      await this.enterFallback("openai_realtime_failure");
    });
  }

  private bindDeepgramEvents(): void {
    this.deepgramClient.on("transcript", async (payload: Record<string, unknown>) => {
      if (!this.controlPlaneSession) {
        return;
      }

      await this.laravelClient.recordEvent(this.controlPlaneSession.id, {
        event_type: "shadow_transcript.partial",
        source: "deepgram",
        payload
      });
    });

    this.deepgramClient.on("error", (error: Error) => {
      providerErrors.inc({ provider: "deepgram" });
      logger.warn({ err: error, sessionId: this.id }, "Deepgram shadow transcript failed.");
    });
  }

  private async enterFallback(mode: string): Promise<void> {
    if (!this.controlPlaneSession) {
      return;
    }

    fallbackRate.inc({ provider: "openai_realtime" });

    await this.laravelClient.recordEvent(this.controlPlaneSession.id, {
      event_type: "session.fallback_entered",
      source: "voice_gateway",
      severity: "warning",
      payload: { mode }
    });

    this.repromptCount += 1;

    if (this.repromptCount <= gatewayConfig.VOICE_GATEWAY_REPROMPT_LIMIT) {
      await this.laravelClient.executeTool("create_callback_request", this.controlPlaneSession.id, {
        phone: this.callerNumber,
        reason: "Realtime session fallback triggered",
        urgency: "normal"
      }, `${this.controlPlaneSession.uuid}:callback:${mode}`);
    }
  }

  private async finalize(status: string): Promise<void> {
    if (!this.controlPlaneSession) {
      this.onClose();
      return;
    }

    this.deepgramClient.close();
    this.openAiClient.close();

    await this.laravelClient.completeSession(this.controlPlaneSession.id, {
      status,
      ended_at: new Date().toISOString(),
      metrics: {
        first_response_latency_ms: this.firstAudioResponseAt ? this.firstAudioResponseAt - this.callStartedAt : null
      }
    });

    this.onClose();
  }
}
