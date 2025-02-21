<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;
    protected $fillable = [
        'token',
        'time',
        'status',
    ];

    public function order_coupon()
    {
        return $this->hasMany(Order_Coupon::class);
    }

}
