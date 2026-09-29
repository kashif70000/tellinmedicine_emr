<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'status',
        'fee_snapshot',
        'notes',
        'subtitle_language',
        'payment_status',
        'paid_at',
        'payout_status',
        'refunded_at',
        'call_started_at',
        'completed_at',
        'duration_seconds',
        'agora_channel',
        'agora_uid',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'appointment_time' => 'datetime:H:i',
            'fee_snapshot' => 'decimal:2',
            'call_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function getDateAttribute()
    {
        return $this->appointment_date;
    }

    public function getTimeAttribute()
    {
        return $this->appointment_time;
    }

    public function getFormattedTimeAttribute(): string
    {
        if (! $this->appointment_time) {
            return '';
        }

        return \Carbon\Carbon::parse($this->appointment_time)->format('g:i A');
    }

    public function getFormattedDurationAttribute(): string
    {
        if ($this->duration_seconds === null) {
            if ($this->call_started_at && $this->completed_at) {
                $seconds = min(1800, max(0, $this->completed_at->timestamp - $this->call_started_at->timestamp));
            } else {
                return 'N/A';
            }
        } else {
            $seconds = $this->duration_seconds;
        }

        $mins = floor($seconds / 60);
        $secs = $seconds % 60;

        if ($mins > 0) {
            return sprintf('%d min%s %02d sec%s', $mins, $mins === 1 ? '' : 's', $secs, $secs === 1 ? '' : 's');
        }

        return sprintf('%d sec%s', $secs, $secs === 1 ? '' : 's');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function prescription()
    {
        return $this->hasOne(Prescription::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }
}
