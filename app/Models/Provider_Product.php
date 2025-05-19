<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider_Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function products()
    {
        return $this->morphMany(Product::class, 'providerable');
    }


    public function discount()
    {
        return $this->morphMany(Disccount::class, 'providerable1');
    }



    public function orders()
    {
        return $this->hasManyThrough(
            Order_Product::class,  // النموذج الوسيط
            Product::class,        // النموذج المرتبط
            'providerable_id',     // المفتاح الأجنبي في Product يشير إلى المزود
            'product_id',          // المفتاح الأجنبي في Order_Product
            'id',                  // المفتاح الأساسي في Provider_Product
            'id'                   // المفتاح الأساسي في Product
        )->where('products.providerable_type', Provider_Product::class);
    }



    public function getCompletedOrdersCountAttribute()
    {
        return $this->orders()->where('status', 'complete')->count();
    }

    public function getPendingOrdersCountAttribute()
    {
        return $this->orders()->where('status', 'pending')->count();
    }

    public function getCancelledOrdersCountAttribute()
    {
        return $this->orders()->where('status', 'cancelled')->count();
    }


    public function getTotalSalesAttribute()
    {
        return $this->orders()->where('status', 'complete')->sum('total_price');
    }


    public function getTotalSalesPendingAttribute()
    {
        return $this->orders()->where('status', 'pending')->sum('total_price');
    }

    // إجمالي العمولات
    public function getTotalCommissionsAttribute()
    {
        return $this->orders()->where('status', 'complete')
            ->with(['product.category'])
            ->get()
            ->sum(function($order) {
                $rate = $order->product->category->price / 100;
                return $order->total_price * $rate;
            });
    }

}
