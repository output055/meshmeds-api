<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReversal extends Model
{
    protected $fillable = ['sale_id', 'user_id', 'amount', 'reason', 'returned_items'];

    protected $casts = [
        'returned_items' => 'array'
    ];

    public function sale() {
        return $this->belongsTo(Sale::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
