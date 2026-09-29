<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RxnormAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'rxcui',
        'atn',
        'atv',
        'sab',
    ];
}
