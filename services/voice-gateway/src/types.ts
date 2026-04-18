export type OpenAiToolDefinition = {
  type: "function";
  name: string;
  description: string;
  parameters: Record<string, unknown>;
};

export type LaravelStartSessionResponse = {
  accepted: boolean;
  reason: string | null;
  voice_session: {
    id: number;
    uuid: string;
    business_id: number;
    voice_channel_id: number | null;
    patient_id: number | null;
  };
  prompts: {
    global: string;
    tenant: string;
    policy: string;
    dynamic: string;
    combined: string;
  };
  tools: OpenAiToolDefinition[];
};

export type TelnyxMessage =
  | {
      event: "start";
      stream_id?: string;
      sequence_number?: string;
      start?: {
        call_control_id?: string;
        call_leg_id?: string;
        from?: string;
        to?: string;
      };
    }
  | {
      event: "media";
      stream_id?: string;
      media?: {
        payload?: string;
        track?: string;
        chunk?: string;
        timestamp?: string;
      };
    }
  | {
      event: "stop";
      stream_id?: string;
      stop?: Record<string, unknown>;
    };

export type OpenAiRealtimeEvent = {
  type: string;
  [key: string]: unknown;
};
