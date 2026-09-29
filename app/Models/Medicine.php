<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    use HasFactory;

    protected $fillable = [
        'rxcui',
        'tty',
        'name',
        'generic_name',
        'brand_name',
        'strength',
        'dosage_form',
        'route',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
