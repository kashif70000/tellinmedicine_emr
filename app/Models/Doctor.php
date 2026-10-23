<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LicenseVerificationAttempt;

class Doctor extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'phone', 'specialization', 'experience', 'fees', 'clinic_name', 'address', 'is_verified', 'rating_avg' , 'certificate_path','verification_status','ai_result','license_number', 'license_state', 'license_type', 'license_issue_date', 'license_expiration_date', 'license_verification_status', 'official_license_status', 'official_license_issue_date', 'official_license_expiration_date', 'license_verification_data', 'license_verification_source', 'license_verification_message', 'license_verified_at', 'uk_verification_status', 'uk_gmc_number', 'uk_verified_name', 'uk_profile_url', 'uk_registration_status', 'uk_licence_status', 'uk_verification_data', 'uk_verification_source', 'uk_verification_message', 'uk_verified_at'];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'fees' => 'decimal:2',
            'rating_avg' => 'float',
            'ai_result' => 'array',
            'license_issue_date' => 'date',
            'license_expiration_date' => 'date',
            'official_license_issue_date' => 'date',
            'official_license_expiration_date' => 'date',
            'license_verification_data' => 'array',
            'license_verified_at' => 'datetime',
            'uk_verification_data' => 'array',
            'uk_verified_at' => 'datetime',

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

    public function licenseVerificationAttempts()
    {
        return $this->hasMany(LicenseVerificationAttempt::class);
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
