<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Counter extends Model
{
    use HasFactory, HasUuids;

    public const CREATED_AT = null;

    protected $fillable = [
        'key',
        'value',
    ];
}
