<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'age', 'gender', 'blood_group', 'is_payment_method_verified', 'phone', 'dob', 'address', 'is_verified'];

    protected function casts(): array
    {
        return [
            'is_payment_method_verified' => 'boolean',
            'is_verified' => 'boolean',
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

    public function doctors()
    {
        return $this->belongsToMany(Doctor::class, 'appointments')
            ->withPivot(['appointment_date', 'appointment_time', 'status', 'notes', 'fee_snapshot'])
            ->withTimestamps();
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

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function patientPharmacies()
    {
        return $this->hasMany(PatientPharmacy::class);
    }

    public function patientLaboratories()
    {
        return $this->hasMany(PatientLaboratory::class);
    }

    public function preferredPharmacy()
    {
        return $this->hasOneThrough(
            Pharmacy::class,
            PatientPharmacy::class,
            'patient_id',
            'id',
            'id',
            'pharmacy_id'
        )->where('patient_pharmacies.is_preferred', true);
    }

    public function preferredLaboratory()
    {
        return $this->hasOneThrough(
            Laboratory::class,
            PatientLaboratory::class,
            'patient_id',
            'id',
            'id',
            'laboratory_id'
        )->where('patient_laboratories.is_preferred', true);
    }

    public function getRouteKey()
    {
        return $this->user?->uuid ?? $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $patient = static::whereHas('user', function ($query) use ($value) {
            $query->where('uuid', $value);
        })->first();

        if ($patient) {
            return $patient;
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
