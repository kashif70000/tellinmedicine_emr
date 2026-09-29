<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NppesHealthcareLocation extends Model
{
    use HasFactory;

    protected $table = 'nppes_healthcare_locations';

    protected $fillable = [
        'npi',
        'entity_type',
        'organization_name',
        'other_organization_name',
        'taxonomy_code',
        'taxonomy_description',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'phone',
        'fax',
        'enumeration_date',
        'last_update_date',
        'source_file',
        'source_row_hash',
    ];

    protected $casts = [
        'enumeration_date' => 'date',
        'last_update_date' => 'date',
    ];
}
