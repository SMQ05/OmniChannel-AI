<?php

declare(strict_types=1);

namespace App\Services\Appointments;

use App\Mail\AppointmentLifecycleMail;
use App\Models\Appointment;
use Illuminate\Support\Facades\Mail;

class AppointmentNotificationService
{
    public function sendLifecycleEmail(Appointment $appointment, string $action, ?Appointment $previousAppointment = null): void
    {
        $appointment->loadMissing(['business', 'patient', 'provider']);

        $email = trim((string) ($appointment->patient->email ?? ''));

        if ($email === '') {
            return;
        }

        Mail::to($email)->send(new AppointmentLifecycleMail(
            business: $appointment->business,
            appointment: $appointment,
            action: $action,
            previousAppointment: $previousAppointment,
        ));
    }
}
