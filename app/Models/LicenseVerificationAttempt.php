<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseVerificationAttempt extends Model
{
    protected $fillable = [
        'doctor_id',
        'actor_user_id',
        'applicant_name',
        'license_state',
        'license_type',
        'license_number',
        'status',
        'message',
        'source',
        'official_data',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'official_data' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}