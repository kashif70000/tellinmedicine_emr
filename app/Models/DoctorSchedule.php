<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model
{
    use HasFactory;
    protected $fillable = [
        'doctor_id',
        'available_date',
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'available_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function getFormattedStartTimeAttribute(): string
    {
        return $this->start_time ? \Carbon\Carbon::parse($this->start_time)->format('g:i A') : '';
    }

    public function getFormattedEndTimeAttribute(): string
    {
        return $this->end_time ? \Carbon\Carbon::parse($this->end_time)->format('g:i A') : '';
    }

    public function getFormattedTimeSlotAttribute(): string
    {
        return $this->formatted_start_time . ' – ' . $this->formatted_end_time;
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
