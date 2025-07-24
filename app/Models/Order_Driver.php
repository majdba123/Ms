<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_Driver extends Model
{
    use HasFactory;
        protected $fillable = [
        'order_id',
        'driver_id',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function driver()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function Order_Product_Driver()
    {
        return $this->hasMany(Order_Product_Driver::class);
    }

}
