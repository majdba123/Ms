<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_Product_Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'order__driver_id',
        'order__product_id',
        'status',
    ];

    public function Order_Driver()
    {
        return $this->belongsTo(Order_Driver::class, 'order__driver_id');
    }


    public function Order_Product()
    {
        return $this->belongsTo(Order_Product::class, 'order__product_id');
    }

}
