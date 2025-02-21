<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'total_price',
        'status',

    ];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function Order_Product()
    {
        return $this->hasMany(Order_Product::class);
    }

    public function Order_Coupon()
    {
        return $this->hasMany(Coupon::class);
    }
}
