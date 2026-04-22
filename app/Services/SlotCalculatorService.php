<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BusinessService;
use App\Models\Provider;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Computes the concrete list of bookable time slots for one or all
 * providers belonging to a business over a rolling 7-day window.
 *
 * Algorithm per provider per day:
 *  1. Skip if the day is not marked active in working_hours.
 *  2. Skip if the date appears in provider_blocked_dates.
 *  3. Generate candidate slots at slot_duration_minutes intervals
 *     between the day's start and end time.
 *  4. Remove any slot that overlaps an existing confirmed/pending
 *     appointment for that provider.
 *
 * Strict rules (locked in memory):
 *  - The 7-day window is anchored to Carbon::now($business->timezone) —
 *    never computed in UTC.
 *  - Output is capped at MAX_SLOTS (50) earliest-first to keep the
 *    LLM context payload bounded.
 *  - Never computes beyond 7 days regardless of the $days argument.
 *
 * All slot timestamps are stored and returned in UTC. AppointmentAgent
 * converts them to the business timezone for patient-facing display.
 *
 * @phpstan-type SlotArray array{
 *     provider_id:   int,
 *     provider_name: string,
 *     date:          string,
 *     start_utc:     string,
 *     end_utc:       string,
 *     label:         string,
 * }
 */
class SlotCalculatorService
{
    /**
     * Hard cap on the number of slots returned to the AI agent.
     * Slots are ordered earliest-first; the tail is dropped when
     * the total exceeds this limit.
     */
    public const MAX_SLOTS = 50;

    /**
     * Hard cap on the lookahead window regardless of caller input.
     */
    public const MAX_DAYS = 7;

    /**
     * Return available slots for the given providers over the next
     * $days calendar days, expressed in UTC, capped at MAX_SLOTS.
     *
     * The window is always anchored to now() in the business timezone,
     * ensuring "day 1" is the correct local calendar day for the tenant.
     *
     * @param  Collection<int, Provider>  $providers  Eager-loaded with blockedDates and appointments
     * @param  string                     $timezone   The business timezone (e.g. 'Asia/Karachi')
     * @param  int                        $days       Lookahead days — silently clamped to MAX_DAYS
     * @param  BusinessService|null       $service    Optional structured service selection
     * @return list<SlotArray>
     */
    public function compute(Collection $providers, string $timezone, int $days = 7, ?BusinessService $service = null): array
    {
        // Enforce the strict 7-day maximum regardless of caller input.
        $days  = min($days, self::MAX_DAYS);
        $slots = [];

        // Anchor to the business's local calendar day — critical for correct
        // day-boundary calculation when the tenant is in a non-UTC timezone.
        $today = CarbonImmutable::now($timezone)->startOfDay();

        for ($offset = 0; $offset < $days; $offset++) {
            $date    = $today->addDays($offset);
            $dayName = strtolower($date->format('l')); // 'monday', 'tuesday', …

            foreach ($providers as $provider) {
                if (!$provider->is_active) {
                    continue;
                }

                if ($service !== null && !$provider->services->contains('id', $service->id)) {
                    continue;
                }

                $daySchedule = $provider->scheduleForDay($dayName);

                if (empty($daySchedule['active'])) {
                    continue;
                }

                if ($this->isBlocked($provider, $date)) {
                    continue;
                }

                $daySlots = $this->buildSlots(
                    provider: $provider,
                    date: $date,
                    timezone: $timezone,
                    startTime: (string) ($daySchedule['start'] ?? '09:00'),
                    endTime: (string) ($daySchedule['end'] ?? '17:00'),
                    service: $service,
                );

                $slots = array_merge($slots, $daySlots);

                // Short-circuit as soon as we have enough slots — no need to
                // build every remaining day if the cap is already reached.
                if (count($slots) >= self::MAX_SLOTS) {
                    return array_slice($slots, 0, self::MAX_SLOTS);
                }
            }
        }

        // Final cap in case the total crept over MAX_SLOTS across the last day.
        return array_slice($slots, 0, self::MAX_SLOTS);
    }

    /**
     * Build the candidate slot list for a single provider on a single day,
     * filtering out slots that conflict with existing appointments.
     *
     * @param  Provider         $provider   The bookable resource
     * @param  CarbonImmutable  $date       The date to generate slots for (business TZ)
     * @param  string           $timezone   Business timezone string
     * @param  string           $startTime  Day start in HH:MM format
     * @param  string           $endTime    Day end in HH:MM format
     * @param  BusinessService|null $service Optional structured service
     * @return list<SlotArray>
     */
    private function buildSlots(
        Provider $provider,
        CarbonImmutable $date,
        string $timezone,
        string $startTime,
        string $endTime,
        ?BusinessService $service = null,
    ): array {
        $duration = $service?->duration_minutes ?? $provider->slot_duration_minutes;
        $stepMinutes = $provider->slot_duration_minutes;
        $bufferBefore = $service?->bookingRuleInt('buffer_before_minutes', 0) ?? 0;
        $bufferAfter = $service?->bookingRuleInt('buffer_after_minutes', 0) ?? 0;

        // Parse working window in business timezone, then convert to UTC
        $windowStart = Carbon::parse(
            $date->format('Y-m-d') . ' ' . $startTime,
            $timezone,
        )->utc();

        $windowEnd = Carbon::parse(
            $date->format('Y-m-d') . ' ' . $endTime,
            $timezone,
        )->utc();

        // Do not offer slots that have already started
        $earliest = Carbon::now()->utc();
        if ($windowStart->lessThan($earliest)) {
            $windowStart = $earliest->copy()->ceilMinutes($stepMinutes);
        }

        if ($windowStart->greaterThanOrEqualTo($windowEnd)) {
            return [];
        }

        // Load booked intervals for this provider on this date (UTC range)
        $bookedIntervals = $this->bookedIntervals($provider, $windowStart, $windowEnd);

        $slots  = [];
        $cursor = $windowStart->copy();

        while ($cursor->copy()->addMinutes($duration)->lessThanOrEqualTo($windowEnd)) {
            $slotEnd = $cursor->copy()->addMinutes($duration);
            $availabilityStart = $cursor->copy()->subMinutes($bufferBefore);
            $availabilityEnd = $slotEnd->copy()->addMinutes($bufferAfter);

            if (!$this->overlapsAnyInterval($availabilityStart, $availabilityEnd, $bookedIntervals)) {
                $slots[] = [
                    'provider_id'   => $provider->id,
                    'provider_name' => $provider->displayName(),
                    'date'          => $date->format('Y-m-d'),
                    'start_utc'     => $cursor->toISOString(),
                    'end_utc'       => $slotEnd->toISOString(),
                    // Human-readable label in UTC; agent converts to business TZ
                    'label'         => $cursor->format('H:i') . ' – ' . $slotEnd->format('H:i') . ' UTC',
                ];
            }

            $cursor->addMinutes($stepMinutes);
        }

        return $slots;
    }

    /**
     * Determine whether the provider has a blocked date entry for the
     * given calendar date.
     *
     * @param  Provider         $provider  Provider with blockedDates relation loaded
     * @param  CarbonImmutable  $date      Date to check (business timezone)
     */
    private function isBlocked(Provider $provider, CarbonImmutable $date): bool
    {
        $ymd = $date->format('Y-m-d');

        return $provider->blockedDates
            ->contains(fn ($bd) => $bd->blocked_date->format('Y-m-d') === $ymd);
    }

    /**
     * Return an array of [start, end] Carbon pairs representing already-booked
     * time for the provider within the given UTC window.
     *
     * Only confirmed and pending appointments are considered — cancelled or
     * completed appointments free up their slot.
     *
     * @param  Provider  $provider      Provider with appointments relation loaded
     * @param  Carbon    $windowStart   UTC window start
     * @param  Carbon    $windowEnd     UTC window end
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    private function bookedIntervals(
        Provider $provider,
        Carbon $windowStart,
        Carbon $windowEnd,
    ): array {
        return $provider->appointments
            ->filter(function ($appt) use ($windowStart, $windowEnd): bool {
                return in_array($appt->status, ['confirmed', 'pending'], true)
                    && $appt->start_time->lessThan($windowEnd)
                    && $appt->end_time->greaterThan($windowStart);
            })
            ->map(fn ($appt): array => [
                Carbon::parse($appt->start_time)->utc(),
                Carbon::parse($appt->end_time)->utc(),
            ])
            ->values()
            ->all();
    }

    /**
     * Determine whether the candidate [slotStart, slotEnd) window overlaps
     * any of the booked intervals.
     *
     * @param  Carbon                          $slotStart
     * @param  Carbon                          $slotEnd
     * @param  list<array{0: Carbon, 1: Carbon}>  $intervals
     */
    private function overlapsAnyInterval(
        Carbon $slotStart,
        Carbon $slotEnd,
        array $intervals,
    ): bool {
        foreach ($intervals as [$bookedStart, $bookedEnd]) {
            // Overlap condition: slot starts before booked ends AND slot ends after booked starts
            if ($slotStart->lessThan($bookedEnd) && $slotEnd->greaterThan($bookedStart)) {
                return true;
            }
        }

        return false;
    }
}
