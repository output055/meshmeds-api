<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Drug extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'barcode', 'category', 'cost_price',        'selling_price',
        'stock_quantity',
        'expiry_date',
        'last_restock_quantity',
    ];
}
