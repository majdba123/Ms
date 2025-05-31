<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Disccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'status',
        'fromtime',
        'totime',
        'value',
        'providerable1_id',
        'providerable1_type'
    ];
    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function providerable1()
    {
        return $this->morphTo();
    }


    public function isActive()
    {
        // تحقق من أن حالة الخصم 'active' (حساس لحالة الأحرف)
        if ($this->status !== 'active') {
            return false;
        }

        return true;
    }
    public function calculateDiscountedPrice($originalPrice)
    {
        if (!$this->isActive()) {
            return $originalPrice;
        }

        $discountAmount = $originalPrice * ($this->value / 100);
        $finalPrice = $originalPrice - $discountAmount;

        // التأكد من أن السعر النهائي ليس أقل من الصفر
        return max($finalPrice, 0);
    }
}
