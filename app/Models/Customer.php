<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'company_name',
        'email',
        'secondary_email',
        'contact_number',
        'secondary_contact',
        'tin_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function addresses()
    {
        return $this->belongsToMany(Address::class, 'customer_addresses');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }
}
