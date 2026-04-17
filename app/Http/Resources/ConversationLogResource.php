<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON:API resource for ConversationLog model.
 *
 * Trims the messages array to the last 10 for list views.
 * The full message history is included when a single log is fetched (/conversations/{id}).
 */
class ConversationLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSingle  = $request->route('conversationLog') !== null;
        $messages  = $this->messages ?? [];

        return [
            'id'               => $this->id,
            'channel'          => $this->channel,
            'human_mode'       => $this->human_mode,
            'ai_model_used'    => $this->ai_model_used,
            'session_started_at' => Carbon::parse($this->session_started_at)->toISOString(),
            'session_ended_at'   => $this->session_ended_at
                ? Carbon::parse($this->session_ended_at)->toISOString()
                : null,
            'message_count'    => count($messages),
            'messages'         => $isSingle ? $messages : array_slice($messages, -3),
            'patient'          => $this->whenLoaded('patient', fn () => [
                'id'   => $this->patient->id,
                'name' => $this->patient->name,
            ]),
        ];
    }
}
