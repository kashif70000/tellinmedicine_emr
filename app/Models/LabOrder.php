<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'patient_id',
        'doctor_id',
        'appointment_id',
        'laboratory_id',
        'status',
        'clinical_notes',
        'ordered_at',
        'laboratory_name_snapshot',
        'laboratory_address_snapshot',
        'laboratory_city_snapshot',
        'laboratory_state_snapshot',
        'laboratory_postal_code_snapshot',
        'laboratory_phone_snapshot',
        'laboratory_selected_at',
        'laboratory_selected_by',
        'email_status',
        'email_sent_at',
        'email_error',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'laboratory_selected_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function items()
    {
        return $this->hasMany(LabOrderItem::class);
    }

    public function labTests()
    {
        return $this->belongsToMany(LabTest::class, 'lab_order_items');
    }

    /**
     * Get destination laboratory display name (snapshot or relational fallback).
     */
    public function getDestinationLaboratoryNameAttribute(): string
    {
        return $this->laboratory_name_snapshot 
            ?: ($this->laboratory?->display_name ?? 'No Laboratory Specified');
    }

    /**
     * Get test count.
     */
    public function getTestCountAttribute(): int
    {
        return $this->items()->count();
    }
}
