<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'drug_id', 'quantity', 'unit_price', 'unit_profit', 'subtotal'];

    public function drug() {
        return $this->belongsTo(Drug::class);
    }
}
