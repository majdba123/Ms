<?php

namespace App\Services\Vendor;

use App\Models\Driver;
use App\Models\Order_Product;
use App\Models\Product;
use App\Models\User;
use App\Models\Provider_Product;
use App\Models\Provider_Service;

class VendorDashboardService
{
    public function getDashboardData(Provider_Product $vendor)
    {
        return [
            'stats' => [
                'completed_orders' => $vendor->completed_orders_count,
                'on_way_order' => $vendor->on_way_orders_count,
                'accepted_orders' => $vendor->accepted_orders_count,
                'done_orders' => $vendor->done_orders_count,

                'pending_orders' => $vendor->pending_orders_count,
                'cancelled_orders' => $vendor->cancelled_orders_count,
                'total_sales_complete' => $vendor->total_sales,
                'total_sales_done' => $vendor->total_sales_done,

                'total_sales_pending' => $vendor->total_sales_pending,
                'total_commissions_complete' => $vendor->total_commissions,
                'total_commissions_done' => $vendor->total_commissions_done,

                'balance' => $vendor->total_sales - $vendor->total_commissions
            ],
        ];
    }



    public function getDashboardData_service(Provider_Service $vendor)
    {
        return [
            'stats' => [
                'completed_orders' => $vendor->completed_reservations_count,
                'pending_orders' => $vendor->pending_reservations_count,
                'cancelled_orders' => $vendor->cancelled_reservations_count,
                'total_sales_complete' => $vendor->total_reservations_revenue,
                'total_sales_pending' => $vendor->pending_reservations_revenue,
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



                'total_service_provider' => Provider_Service::count(),
                'active_service_provider' => Provider_Service::where('status', 'active')->count(),
                'pending_service_provider' => Provider_Service::where('status', 'pending')->count(),
                'banned_service_provider' => Provider_Service::where('status', 'banned')->count(),


                'total_Driver' => Driver::count(),
                'active_Driver' => Driver::where('status', 'active')->count(),
                'pending_Driver' => Driver::where('status', 'pending')->count(),
                'banned_Driver' => Driver::where('status', 'banned')->count(),

                // إحصائيات المنتجات
                // إحصائيات المنتجات
                'products_stats' => [
                    'total_products' => Product::count(),
                    'by_provider_type' => [
                        'service_providers' => Product::where('providerable_type', 'App\Models\Provider_Service')->count(),
                        'product_providers' => Product::where('providerable_type', 'App\Models\Provider_Product')->count(),
                    ],
                ],

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
