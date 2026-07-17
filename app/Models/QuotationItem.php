<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'quotation_id',
        'product_id',
        'sort_order',
        'description',
        'image_url',
        'unit',
        'rate',
        'quantity',
        'discount_rate',
        'is_tax_included',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'quantity' => 'integer',
            'discount_rate' => 'decimal:2',
            'is_tax_included' => 'boolean',
            'line_total' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
