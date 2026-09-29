<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RxnormImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_version',
        'source',
        'imported_at',
        'status',
        'records_imported',
        'records_updated',
        'records_failed',
        'notes',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
        'records_imported' => 'integer',
        'records_updated' => 'integer',
        'records_failed' => 'integer',
    ];
}
