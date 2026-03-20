<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = ['receipt_number', 'user_id', 'subtotal', 'total_profit', 'payment_method', 'amount_tendered', 'change_due', 'status'];

    protected $attributes = [
        'status' => 'Completed',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
