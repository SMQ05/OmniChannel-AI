@php
    $start = $appointment->start_time?->copy()->setTimezone($business->timezone);
    $previousStart = $previousAppointment?->start_time?->copy()->setTimezone($business->timezone);
    $heading = match ($action) {
        'created' => 'Appointment confirmed',
        'cancelled' => 'Appointment cancelled',
        'rescheduled' => 'Appointment rescheduled',
        'reminder' => 'Appointment reminder',
        default => 'Appointment update',
    };
@endphp

<x-mail::message>
# {{ $heading }}

Hi {{ $appointment->patient->name ?? 'there' }},

{{ $business->name }} has updated your appointment.

<x-mail::panel>
Service: {{ $appointment->service_type }}  
Provider: {{ $appointment->provider->displayName() }}  
Date: {{ $start?->format('l, F j, Y') }}  
Time: {{ $start?->format('g:i A') }}  
Status: {{ ucfirst($appointment->status) }}
</x-mail::panel>

@if($action === 'rescheduled' && $previousStart !== null)
Previous time: {{ $previousStart->format('l, F j, Y g:i A') }}
@endif

If you need to make another change, reply through your booking channel or contact {{ $business->name }} directly.

Thanks,<br>
{{ $business->name }}
</x-mail::message>
