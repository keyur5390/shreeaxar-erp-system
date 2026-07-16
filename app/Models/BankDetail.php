<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankDetail extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'bank_name',
        'account_number',
        'account_holder_name',
        'branch_name',
        'swift_code',
        'is_active',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }
}
