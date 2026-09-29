<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'phone', 'specialization', 'experience', 'fees', 'clinic_name', 'address', 'is_verified', 'rating_avg' , 'certificate_path','verification_status','ai_result','license_number',];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'fees' => 'decimal:2',
            'rating_avg' => 'float',
            'ai_result' => 'array',

        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function patients()
    {
        return $this->belongsToMany(Patient::class, 'appointments')
            ->withPivot(['appointment_date', 'appointment_time', 'status', 'notes', 'fee_snapshot'])
            ->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class);
    }

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->user?->name;
        if (!$name) {
            return '—';
        }
        return \Illuminate\Support\Str::startsWith($name, ['Dr.', 'Dr ']) ? $name : 'Dr. ' . $name;
    }
}
