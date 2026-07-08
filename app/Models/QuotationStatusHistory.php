<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationStatusHistory extends Model
{
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [
        'quotation_id',
        'from_status_id',
        'to_status_id',
        'changed_by_id',
        'note',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    public function fromStatus()
    {
        return $this->belongsTo(QuotationStatus::class, 'from_status_id');
    }

    public function toStatus()
    {
        return $this->belongsTo(QuotationStatus::class, 'to_status_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
