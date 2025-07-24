<?php

namespace App\Http\Controllers;

use App\Models\Order_Product;
use App\Models\Provider_Product;
use Illuminate\Http\Request;

class OrderProductController extends Controller
{
    public function getAllVendorsOrders()
    {
        // الحصول على جميع الطلبات pending لكل التجار
        $orders = Order_Product::with([
                'order:id,user_id,status,created_at',
                'product:id,name,providerable_id,providerable_type',
                'product.providerable.user:id,name' // معلومات التاجر
            ])
            ->whereHas('product', function($query) {
                $query->whereNotNull('providerable_id')
                    ->whereNotNull('providerable_type')
                    ->where('providerable_type', Provider_Product::class);
            })
            ->where('status', 'pending')
            ->get();

        // تجميع الطلبات حسب order_id ثم حسب التاجر
        $groupedOrders = $orders->groupBy('order_id')->map(function ($orderItems) {
            // تجميع المنتجات حسب التاجر داخل كل طلب
            return $orderItems->groupBy(function($item) {
                return $item->product->providerable_id;
            })->map(function ($vendorItems, $vendorId) {
                $firstItem = $vendorItems->first();
                $vendor = $firstItem->product->providerable;

                return [
                    'order_id' => $firstItem->order_id,
                    'order_details' => $firstItem->order,
                    'vendor' => [
                        'id' => $vendor->id,
                        'name' => $vendor->user->name ?? 'Unknown Vendor',
                    ],
                    'products' => $vendorItems->map(function ($item) {
                        return [
                            'order_product_id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name,
                            'total_price' => $item->total_price,
                            'quantity' => $item->quantity,
                            'status' => $item->status,
                            'created_at' => $item->created_at
                        ];
                    })
                ];
            });
        })->collapse()->values();

        return response()->json(['orders' => $groupedOrders], 200);
    }




}
