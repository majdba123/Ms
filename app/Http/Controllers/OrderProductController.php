<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Order_Product;
use App\Models\Provider_Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function index()
    {
        // التحقق من وجود إحداثيات السائق
        $driver = Auth::user();
        if (empty($driver->lat) || empty($driver->lang)) {
            return response()->json([
                'success' => false,
                'message' => 'يجب على السائق تحديث موقعه الجغرافي أولاً'
            ], 400);
        }

        // الحصول على الطلبات المعلقة مع العلاقات المطلوبة
        $orders = Order::with([
            'user:id,name,phone,lat,lang', // إضافة معلومات إضافية للمستخدم
            'Order_Product.product.providerable.user:id,name,lat,lang',
            'coupons' // إضافة العلاقة مع الكوبونات
        ])
        ->where('status', 'pending')
        ->get();

        // معالجة البيانات وإضافة معلومات المسافة
        $processedOrders = $orders->map(function ($order) use ($driver) {
            // حساب المسافة بين السائق وصاحب الطلب
            $distanceToUser = $this->calculateDistance(
                $driver->lat,
                $driver->lang,
                $order->user->lat,
                $order->user->lang
            );

            // تجميع معلومات التجار مع المسافات
            $vendors = collect();
            $products = collect();

            foreach ($order->Order_Product as $orderProduct) {
                if ($orderProduct->product && $orderProduct->product->providerable) {
                    $vendor = $orderProduct->product->providerable;

                    $distanceToVendor = $this->calculateDistance(
                        $driver->lat,
                        $driver->lang,
                        $vendor->user->lat,
                        $vendor->user->lang
                    );

                    $vendors->push([
                        'vendor_id' => $vendor->id,
                        'vendor_name' => $vendor->user->name,
                        'distance_to_driver' => $distanceToVendor,
                        'coordinates' => [
                            'lat' => $vendor->user->lat,
                            'lng' => $vendor->user->lang
                        ]
                    ]);

                    $products->push([
                        'product_id' => $orderProduct->product_id,
                        'product_name' => $orderProduct->product->name,
                        'quantity' => $orderProduct->quantity,
                        'unit_price' => $orderProduct->unit_price,
                        'total_price' => $orderProduct->total_price,
                        'vendor_id' => $vendor->id
                    ]);
                }
            }

            // جمع معلومات الكوبونات إذا وجدت
            $coupons = $order->coupons->map(function($coupon) {
                return [
                    'coupon_id' => $coupon->id,
                    'code' => $coupon->code,
                    'discount_amount' => $coupon->pivot->discount_amount ?? 0
                ];
            });

            return [
                'order_info' => [
                    'order_id' => $order->id,
                    'total_price' => $order->total_price,
                    'delivery_fee' => $order->delivery_fee,
                    'final_price' => $order->total_price + $order->delivery_fee,
                    'status' => $order->status,
                    'note' => $order->note,
                    'created_at' => $order->created_at,
                    'coupons' => $coupons
                ],
                'user_info' => [
                    'user_id' => $order->user->id,
                    'name' => $order->user->name,
                    'phone' => $order->user->phone,
                    'distance_to_driver' => $distanceToUser,
                    'coordinates' => [
                        'lat' => $order->user->lat,
                        'lng' => $order->user->lang
                    ]
                ],
                'vendors' => $vendors,
                'products' => $products,
                'nearest_vendor_distance' => $vendors->min('distance_to_driver') ?? 0
            ];
        });

        // ترتيب الطلبات حسب الأقرب (أقرب تاجر في كل طلب)
        $sortedOrders = $processedOrders->sortBy('nearest_vendor_distance')->values();

        return response()->json([
            'success' => true,
            'driver_info' => [
                'driver_id' => $driver->Driver->id,
                'name' => $driver->name,
                'coordinates' => [
                    'lat' => $driver->lat,
                    'lng' => $driver->lang
                ]
            ],
            'orders' => $sortedOrders,
            'total_orders' => $sortedOrders->count()
        ]);
    }
private function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371000; // نصف قطر الأرض بالأمتار

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    $distanceInMeters = $earthRadius * $c;
    $distanceInKm = $distanceInMeters / 1000; // التحويل من أمتار إلى كيلومترات

    return round($distanceInKm, 2); // تقريب إلى منزلتين عشريتين
}


}
