<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Product;
use App\Models\Order_Product;
use Illuminate\Support\Facades\Auth;

class OrderService
{
    public function createOrder(array $validatedData)
    {
        $userId = Auth::id();
        // إنشاء الطلب
        $order = Order::create([
            'user_id' => $userId,
            'total_price' => 0, // سيتم تحديثه لاحقاً
            'status' => 'pending', // أو الحالة التي تريدها
        ]);

        // حساب سعر المنتجات وإضافتها إلى الطلب
        $totalPrice = 0;
        foreach ($validatedData['products'] as $productData) {
            $product = Product::find($productData['product_id']);
            $orderProduct = Order_Product::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $productData['quantity'],
                'total_price' => $product->price * $productData['quantity'],
            ]);
            $totalPrice += $orderProduct->total_price;
        }

        // تحديث سعر الطلب الكلي
        $order->update(['total_price' => $totalPrice]);

        return $order;
    }
}
