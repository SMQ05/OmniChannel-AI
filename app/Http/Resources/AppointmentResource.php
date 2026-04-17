<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON:API resource for Appointment model.
 *
 * Outputs times in both UTC (for API consumers) and the business timezone
 * (for display). Calendar-related fields are included so Alpine.js can
 * colour-code events by provider without extra requests.
 */
class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone   = $this->business?->timezone ?? $request->user()?->business?->timezone ?? 'UTC';
        $startLocal = Carbon::parse($this->start_time)->setTimezone($timezone);
        $endLocal   = Carbon::parse($this->end_time)->setTimezone($timezone);

        return [
            'id'                  => $this->id,
            'service_type'        => $this->service_type,
            'status'              => $this->status,
            'booked_via'          => $this->booked_via,
            'start_time_utc'      => Carbon::parse($this->start_time)->utc()->toISOString(),
            'end_time_utc'        => Carbon::parse($this->end_time)->utc()->toISOString(),
            'start_time_local'    => $startLocal->toISOString(),
            'end_time_local'      => $endLocal->toISOString(),
            'date_display'        => $startLocal->format('l, F j, Y'),
            'time_display'        => $startLocal->format('g:i A'),
            'synced_to_calendar'  => $this->synced_to_calendar,
            'synced_to_sheets'    => $this->synced_to_sheets,
            'notes'               => $this->when(
                $request->user()?->role !== 'staff',
                $this->notes,
            ),
            'patient'             => $this->whenLoaded('patient', fn () => [
                'id'    => $this->patient->id,
                'name'  => $this->patient->name,
                'phone' => $this->patient->phone,
            ]),
            'provider'            => $this->whenLoaded('provider', fn () => [
                'id'           => $this->provider->id,
                'name'         => $this->provider->name,
                'display_name' => $this->provider->displayName(),
                'color'        => $this->providerColor($this->provider->id),
            ]),
        ];
    }

    /**
     * Generate a deterministic hex colour for a provider based on their ID.
     *
     * Uses a fixed palette to ensure stable colours across renders.
     *
     * @param  int  $providerId
     * @return string  Hex colour e.g. '#6366f1'
     */
    private function providerColor(int $providerId): string
    {
        $palette = [
            '#6366f1', '#ec4899', '#14b8a6', '#f59e0b',
            '#10b981', '#3b82f6', '#8b5cf6', '#ef4444',
        ];

        return $palette[$providerId % count($palette)];
    }
}
