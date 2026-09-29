<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NppesImport extends Model
{
    use HasFactory;

    protected $table = 'nppes_imports';

    protected $fillable = [
        'source_file',
        'file_hash',
        'import_started_at',
        'import_completed_at',
        'rows_scanned',
        'rows_imported',
        'pharmacy_rows',
        'laboratory_rows',
        'status',
        'notes',
    ];

    protected $casts = [
        'import_started_at' => 'datetime',
        'import_completed_at' => 'datetime',
        'rows_scanned' => 'integer',
        'rows_imported' => 'integer',
        'pharmacy_rows' => 'integer',
        'laboratory_rows' => 'integer',
    ];
}
