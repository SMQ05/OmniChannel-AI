import { EventEmitter } from "node:events";
import WebSocket from "ws";

export class DeepgramShadowClient extends EventEmitter {
  private socket?: WebSocket;

  constructor(private readonly apiKey?: string) {
    super();
  }

  async connect(): Promise<void> {
    if (!this.apiKey) {
      return;
    }

    this.socket = new WebSocket(
      "wss://api.deepgram.com/v1/listen?model=flux&encoding=mulaw&sample_rate=8000&channels=1",
      {
        headers: {
          Authorization: `Token ${this.apiKey}`
        }
      }
    );

    await new Promise<void>((resolve, reject) => {
      this.socket?.once("open", () => resolve());
      this.socket?.once("error", reject);
    });

    this.socket.on("message", (raw) => {
      const payload = JSON.parse(String(raw)) as Record<string, unknown>;
      this.emit("transcript", payload);
    });

    this.socket.on("error", (error) => this.emit("error", error));
  }

  sendMulawFrame(base64Audio: string): void {
    if (!this.socket || this.socket.readyState !== WebSocket.OPEN) {
      return;
    }

    this.socket.send(Buffer.from(base64Audio, "base64"));
  }

  close(): void {
    this.socket?.close();
  }
}
