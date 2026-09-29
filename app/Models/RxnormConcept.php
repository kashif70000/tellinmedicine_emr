<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RxnormConcept extends Model
{
    use HasFactory;

    protected $primaryKey = 'rxcui';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'rxcui',
        'name',
        'tty',
        'suppress',
        'sab',
    ];
}
