<?php

namespace App\Services\Vendor;

use App\Models\Order_Product;
use App\Models\Product;
use App\Models\User;
use App\Models\Provider_Product;

class VendorDashboardService
{
    public function getDashboardData(Provider_Product $vendor)
    {
        return [
            'stats' => [
                'completed_orders' => $vendor->completed_orders_count,
                'pending_orders' => $vendor->pending_orders_count,
                'cancelled_orders' => $vendor->cancelled_orders_count,
                'total_sales_complete' => $vendor->total_sales,
                'total_sales_pending' => $vendor->total_sales_pending,
                'total_commissions_complete' => $vendor->total_commissions,
                'balance' => $vendor->total_sales - $vendor->total_commissions
            ],
        ];
    }













    public function getDashboardadmin()
    {
        return [
            'stats' => [
                // إحصائيات المستخدمين
                'total_users' => User::count(),
             //   'active_users' => User::where('stat', true)->count(),
             //   'inactive_users' => User::where('is_active', false)->count(),
             'active_users' => "not yet",
             'inactive_users' => "not yet",

                // إحصائيات التجار
                'total_vendors' => Provider_Product::count(),
                'active_vendors' => Provider_Product::where('status', 'active')->count(),
                'pending_vendors' => Provider_Product::where('status', 'pending')->count(),
                'banned_vendors' => Provider_Product::where('status', 'banned')->count(),

                // إحصائيات المنتجات
                'total_products' => Product::count(),
               // 'active_products' => Product::where('status', 'active')->count(),
               // 'inactive_products' => Product::where('status', 'inactive')->count(),

                'active_products' =>"not yet",
                'inactive_products' => "not yet",

                // إحصائيات الطلبات
                'total_orders' => Order_Product::count(),
                'completed_orders' => Order_Product::where('status', 'complete')->count(),
                'pending_orders' => Order_Product::where('status', 'pending')->count(),
                'cancelled_orders' => Order_Product::where('status', 'cancelled')->count(),
                'total_sales' => Order_Product::where('status', 'complete')->sum('total_price'),

                // إحصائيات مالية
                'total_commissions' => $this->calculateTotalCommissions(),
                'pending_commissions' => $this->calculatePendingCommissions(),
            ],
            'recent_orders' => Order_Product::with(['product'])
                ->latest()
                ->take(10)
                ->get(),
            'recent_vendors' => Provider_Product::with(['user'])
                ->latest()
                ->take(5)
                ->get()
        ];
    }

    protected function calculateTotalCommissions()
    {
        return Order_Product::where('status', 'complete')
            ->with(['product.Category'])
            ->get()
            ->sum(function($order) {
                $rate = $order->product->category->price / 100;
                return $order->total_price * $rate;
            });
    }

    protected function calculatePendingCommissions()
    {
        return Order_Product::where('status', 'pending')
            ->with(['product.Category'])
            ->get()
            ->sum(function($order) {
                $rate = $order->product->category->price / 100;
                return $order->total_price * $rate;
            });
    }
}
