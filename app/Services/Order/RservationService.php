<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Product;
use App\Models\Order_Product;
use App\Models\Rseevation;

use Illuminate\Support\Facades\Auth;

class RservationService
{
    public function createOrder(array $validatedData)
    {
        $userId = Auth::id();
        // إنشاء الطلب
        $order = Rseevation::create([
            'user_id' => $userId,
            'product_id' => 0, // سيتم تحديثه لاحقاً
            'status' => 'pending', // أو الحالة التي تريدها
        ]);
        // تحديث سعر الطلب الكلي

        return $order;
    }
}
