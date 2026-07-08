<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuotationItem extends Model { protected $fillable=['quotation_id','product_name','description','material','finish','width_mm','height_mm','depth_mm','quantity','unit_price','tax_rate','line_total','image_path']; protected function casts(): array { return ['quantity'=>'integer','unit_price'=>'decimal:2','tax_rate'=>'decimal:2','line_total'=>'decimal:2']; } public function quotation(){return $this->belongsTo(Quotation::class);} }
