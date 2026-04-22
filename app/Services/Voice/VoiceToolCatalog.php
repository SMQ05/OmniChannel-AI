<?php

declare(strict_types=1);

namespace App\Services\Voice;

class VoiceToolCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            $this->tool(
                'check_availability',
                'Check available appointment slots for a business or provider.',
                [
                    'type' => 'object',
                    'properties' => [
                        'provider_id' => ['type' => 'integer'],
                        'service_id' => ['type' => 'integer'],
                        'service_type' => ['type' => 'string'],
                        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 7],
                    ],
                ],
            ),
            $this->tool(
                'book_appointment',
                'Book an appointment only after the caller confirms provider, date, and time.',
                [
                    'type' => 'object',
                    'properties' => [
                        'patient_id' => ['type' => 'integer'],
                        'phone' => ['type' => 'string'],
                        'provider_id' => ['type' => 'integer'],
                        'service_id' => ['type' => 'integer'],
                        'date' => ['type' => 'string'],
                        'time' => ['type' => 'string'],
                        'service_type' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['provider_id', 'date', 'time', 'service_type'],
                ],
            ),
            $this->tool(
                'reschedule_appointment',
                'Reschedule an existing appointment after identifying the appointment and the replacement slot.',
                [
                    'type' => 'object',
                    'properties' => [
                        'appointment_id' => ['type' => 'integer'],
                        'patient_id' => ['type' => 'integer'],
                        'phone' => ['type' => 'string'],
                        'provider_id' => ['type' => 'integer'],
                        'service_id' => ['type' => 'integer'],
                        'date' => ['type' => 'string'],
                        'time' => ['type' => 'string'],
                        'service_type' => ['type' => 'string'],
                    ],
                    'required' => ['date', 'time'],
                ],
            ),
            $this->tool(
                'cancel_appointment',
                'Cancel an appointment only when the matching appointment is identified.',
                [
                    'type' => 'object',
                    'properties' => [
                        'appointment_id' => ['type' => 'integer'],
                        'patient_id' => ['type' => 'integer'],
                        'phone' => ['type' => 'string'],
                        'provider_id' => ['type' => 'integer'],
                    ],
                ],
            ),
            $this->tool(
                'lookup_patient',
                'Look up an existing patient record by id or phone number.',
                [
                    'type' => 'object',
                    'properties' => [
                        'patient_id' => ['type' => 'integer'],
                        'phone' => ['type' => 'string'],
                    ],
                ],
            ),
            $this->tool(
                'get_business_faq',
                'Fetch the business FAQ and training answers relevant to the caller question.',
                [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                    ],
                ],
            ),
            $this->tool(
                'create_callback_request',
                'Create a callback request for the clinic team with reason, urgency, and phone number.',
                [
                    'type' => 'object',
                    'properties' => [
                        'phone' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                        'urgency' => ['type' => 'string'],
                        'preferred_time' => ['type' => 'string'],
                    ],
                    'required' => ['phone', 'reason'],
                ],
            ),
            $this->tool(
                'notify_staff',
                'Create an internal staff-notification event for human follow-up.',
                [
                    'type' => 'object',
                    'properties' => [
                        'message' => ['type' => 'string'],
                        'department' => ['type' => 'string'],
                        'urgency' => ['type' => 'string'],
                    ],
                    'required' => ['message'],
                ],
            ),
            $this->tool(
                'send_followup_whatsapp',
                'Send a WhatsApp follow-up only when a valid WhatsApp recipient is known.',
                [
                    'type' => 'object',
                    'properties' => [
                        'recipient_platform_id' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                    ],
                    'required' => ['message'],
                ],
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputSchema
     * @return array<string, mixed>
     */
    private function tool(string $name, string $description, array $inputSchema): array
    {
        return [
            'type' => 'function',
            'name' => $name,
            'description' => $description,
            'parameters' => $inputSchema,
        ];
    }
}
