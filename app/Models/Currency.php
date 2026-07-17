<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'exchange_rate',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'exchange_rate' => 'decimal:8',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class);
    }

    public function snapshot(): array
    {
        return [
            'code' => $this->code,
            'symbol' => $this->symbol,
            'decimal_places' => (int) $this->decimal_places,
            'exchange_rate' => (float) $this->exchange_rate,
        ];
    }
}
