<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyDetail extends Model
{
    use HasFactory;

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'logo',
        'email',
        'phone',
        'address',
        'tin_number',
        'vat_number',
        'website',
    ];
}
