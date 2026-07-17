<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'quotation_number',
        'customer_id',
        'status_id',
        'quotation_date',
        'expiry_date',
        'authorized_by_id',
        'bank_detail_id',
        'bank_snapshot',
        'terms_conditions',
        'notes',
        'sub_total',
        'vat_amount',
        'discount_amount',
        'total_amount',
        'currency_id',
        'exchange_rate',
        'currency_snapshot',
        'vat_rate',
        'revision_number',
        'last_modified_at',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'expiry_date' => 'date',
            'bank_snapshot' => 'array',
            'sub_total' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'currency_snapshot' => 'array',
            'vat_rate' => 'decimal:2',
            'last_modified_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'revision_number' => 'integer',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function status()
    {
        return $this->belongsTo(QuotationStatus::class, 'status_id');
    }

    public function authorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by_id');
    }

    public function bankDetail()
    {
        return $this->belongsTo(BankDetail::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function statusHistory()
    {
        return $this->hasMany(QuotationStatusHistory::class)->orderBy('created_at');
    }
}
