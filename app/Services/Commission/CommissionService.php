<?php

namespace App\Services\Commission;

use App\Models\Order_Product;
use App\Models\Product;
use App\Models\Provider_Product;
use Illuminate\Support\Facades\Auth;

class CommissionService
{
    public function calculateVendorCommission(Provider_Product $vendor, string $status, $startDate = null, $endDate = null)
    {
        // جلب طلبات التاجر مع التصفية حسب الحالة
        $orders = $vendor->orders()
        ->when($status !== 'all', function ($query) use ($status) {
            $query->where('order__products.status', $status); // تحديد الجدول هنا
        })
        ->when($startDate, function ($query) use ($startDate) {
            $query->whereDate('order__products.created_at', '>=', $startDate); // تحديد الجدول هنا
        })
        ->when($endDate, function ($query) use ($endDate) {
            $query->whereDate('order__products.created_at', '<=', $endDate); // تحديد الجدول هنا
        })
        ->with(['product.category'])
        ->get();

        $totalSales = 0;
        $totalCommission = 0;
        $commissionDetails = [];

        foreach ($orders as $order) {
            // التحقق من وجود المنتج والتصنيفات المرتبطة به
            if (!$order->product || !$order->product->category) {
                continue;
            }

            $product = $order->product;
            $category = $product->category;

            $commissionRate = $category->price / 100;
            $orderCommission = $order->total_price * $commissionRate;

            $totalSales += $order->total_price;
            $totalCommission += $orderCommission;

            $commissionDetails[] = [
                'order_id' => $order->order_id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'category_id' => $category->id,
                'category_name' => $category->title,
                'commission_rate' => $category->price,
                'order_amount' => $order->total_price,
                'commission' => $orderCommission,
                'date' => $order->created_at->toDateString(),
                'status' => $order->status // إضافة حالة الطلب للتوضيح
            ];
        }

        return [
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->user->name ?? 'N/A', // تحقق آمن من وجود المستخدم
            'total_sales' => $totalSales,
            'total_commission' => $totalCommission,
            'commission_details' => $commissionDetails,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'filter' => [
                'status' => $status,
                'orders_count' => count($commissionDetails)
            ]
        ];
    }





    public function calculateProductCommission(Provider_Product $vendor, Product $product, string $status = 'complete')
    {
        // التحقق من أن المنتج يخص التاجر


        $category = $product->category;
        $commissionRate = $category->price / 100;

        // بناء الاستعلام مع التصفية حسب الحالة
        $query = $product->order_product()
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status); // تصفية حسب الحالة إذا لم تكن 'all'
            })
            ->with(['order']);

        $orderProducts = $query->get();
        $totalSales = $orderProducts->sum('total_price');
        $totalCommission = $totalSales * $commissionRate;

        $orderDetails = $orderProducts->map(function ($orderProduct) use ($commissionRate) {
            return [
                'order_product_id' => $orderProduct->order_id,
                'amount' => $orderProduct->total_price,
                'quantity' => $orderProduct->quantity,
                'commission' => $orderProduct->total_price * $commissionRate,
                'date' => $orderProduct->created_at->toDateString(),
                'status' => $orderProduct->status // إضافة حالة الطلب للتفاصيل
            ];
        });

        return [
            'success' => true,
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'category' => $category->title,
                    'commission_rate' => $category->price . '%'
                ],
                'vendor' => [
                    'id' => $vendor->id,
                    'name' => $vendor->user->name ?? 'N/A'
                ],
                'total_sales' => $totalSales,
                'total_commission' => $totalCommission,
                'orders_count' => $orderProducts->count(),
                'orders' => $orderDetails,
                'filter' => [
                    'status' => $status,
                    'applied_filter' => $status === 'all' ? 'جميع الطلبات' : "طلبات بحالة: $status"
                ]
            ]
        ];
    }
}
