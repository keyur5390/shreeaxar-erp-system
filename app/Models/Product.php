<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'rate',
        'currency_id',
        'is_tax_included',
        'unit_id',
        'model_number',
        'description',
        'primary_image',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_tax_included' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function quotationItems()
    {
        return $this->hasMany(QuotationItem::class);
    }
}
