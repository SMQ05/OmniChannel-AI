<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Appointment;
use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentLifecycleMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Business $business,
        public readonly Appointment $appointment,
        public readonly string $action,
        public readonly ?Appointment $previousAppointment = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->action) {
            'created' => 'Appointment confirmed',
            'cancelled' => 'Appointment cancelled',
            'rescheduled' => 'Appointment rescheduled',
            'reminder' => 'Appointment reminder',
            default => 'Appointment update',
        };

        return new Envelope(subject: $subject . ' - ' . $this->business->name);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.appointments.lifecycle',
        );
    }
}
