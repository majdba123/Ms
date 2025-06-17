<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'order_id',
        'quantity',
        'status',
        'original_price',    // السعر الأصلي قبل الخصم
        'unit_price',       // سعر الوحدة بعد الخصم
        'total_price',      // السعر الإجمالي للكمية
        'discount_applied', // هل تم تطبيق خصم
        'discount_value',   // قيمة الخصم
        'discount_type',    // نوع الخصم (percentage, fixed, etc)
    ];


    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // دالة مساعدة للتحقق من وجود خصم (اختياري)
    public function hasDiscount(): bool
    {
        return $this->discount_applied && $this->discount_value > 0;
    }

    // دالة لحساب مقدار الخصم (اختياري)
    public function discountAmount(): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        return $this->original_price * $this->quantity - $this->total_price;
    }
}
