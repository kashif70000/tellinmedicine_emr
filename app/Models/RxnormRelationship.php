<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RxnormRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'rxcui1',
        'rxcui2',
        'rel',
        'rela',
        'sab',
    ];
}
