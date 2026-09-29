<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientPharmacy extends Model
{
    use HasFactory;

    protected $table = 'patient_pharmacies';

    protected $fillable = [
        'patient_id',
        'pharmacy_id',
        'is_preferred',
    ];

    protected function casts(): array
    {
        return [
            'is_preferred' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
