<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientLaboratory extends Model
{
    use HasFactory;

    protected $table = 'patient_laboratories';

    protected $fillable = [
        'patient_id',
        'laboratory_id',
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

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }
}
