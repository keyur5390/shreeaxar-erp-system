<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
class Customer extends Model { use LogsActivity; protected $fillable=['company_name','contact_name','email','phone','billing_address','shipping_address','gst_number']; public function quotations(){return $this->hasMany(Quotation::class);} public function getActivitylogOptions(): LogOptions { return LogOptions::defaults()->logFillable()->logOnlyDirty(); } }
