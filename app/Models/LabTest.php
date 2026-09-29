<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'loinc_code',
        'category',
        'description',
        'specimen',
        'is_panel',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_panel' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
