<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAction extends Model
{
    protected $table = 'booking_actions';

    protected $fillable = [
        'business_id',
        'patient_id',
        'appointment_id',
        'action',
        'source_channel',
        'idempotency_key',
        'status',
        'request_payload',
        'result_payload',
        'last_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'result_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
