<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_Coupon extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'coupon_id',
        'price_bef',
        'price_aft',

    ];
    public function order()
    {
        return $this->belongsTo(Order::class,'order_id');
    }
    public function coupon()
    {
        return $this->belongsTo(Coupon::class,'coupon_id');
    }
}
