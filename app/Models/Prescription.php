<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'appointment_id',
        'doctor_id',
        'patient_id',
        'pharmacy_id',
        'pharmacy_name_snapshot',
        'pharmacy_address_snapshot',
        'pharmacy_city_snapshot',
        'pharmacy_state_snapshot',
        'pharmacy_postal_code_snapshot',
        'pharmacy_phone_snapshot',
        'pharmacy_selected_at',
        'pharmacy_selected_by',
        'diagnosis',
        'notes',
        'medicines',
        'status',
        'source',
        'email_status',
        'email_sent_at',
        'email_error',
    ];

    protected function casts(): array
    {
        return [
            'medicines' => 'array',
            'pharmacy_selected_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /**
     * Get the destination pharmacy display name (snapshot or relational fallback).
     */
    public function getDestinationPharmacyNameAttribute(): string
    {
        return $this->pharmacy_name_snapshot 
            ?: ($this->pharmacy?->display_name ?? 'No Pharmacy Specified');
    }

    /**
     * Format medication count for history tables.
     */
    public function getMedicationCountAttribute(): int
    {
        return is_array($this->medicines) ? count($this->medicines) : 0;
    }
}
