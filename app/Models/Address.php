<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'address_type_id',
        'address_line_1',
        'address_line_2',
        'country_id',
        'state_id',
        'city',
        'postal_code',
    ];

    public function addressType()
    {
        return $this->belongsTo(AddressType::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
