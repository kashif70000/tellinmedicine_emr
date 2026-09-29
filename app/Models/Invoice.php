<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $fillable = [
        'payment_id',
        'appointment_id',
        'patient_id',
        'invoice_number',
        'total_amount',
        'issued_date',
        'status',
        'email_sent',
        'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'issued_date'  => 'date',
            'email_sent'   => 'boolean',
            'emailed_at'   => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    // ── Helpers ────────────────────────────────────────────────────

    /**
     * Generate a unique invoice number: INV-YYYYMM-NNNNN
     */
    public static function generateNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ym') . '-';
        $last   = static::where('invoice_number', 'like', $prefix . '%')
                        ->orderByDesc('invoice_number')
                        ->value('invoice_number');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
