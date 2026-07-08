<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class Quotation extends Model { use LogsActivity; protected $fillable=['customer_id','quotation_number','status','issue_date','expiry_date','subtotal','tax_total','discount_total','grand_total','notes']; protected function casts(): array { return ['issue_date'=>'date','expiry_date'=>'date','subtotal'=>'decimal:2','tax_total'=>'decimal:2','discount_total'=>'decimal:2','grand_total'=>'decimal:2']; } public function customer(){return $this->belongsTo(Customer::class);} public function items(){return $this->hasMany(QuotationItem::class);} public function getActivitylogOptions(): LogOptions { return LogOptions::defaults()->logFillable()->logOnlyDirty(); } }
