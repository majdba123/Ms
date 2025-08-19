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

    // التحقق من وجود البروفايل والإحداثيات
    if (empty($driver->lat) || empty($driver->lang)) {
        return response()->json([
            'success' => false,
            'message' => 'يجب على السائق تحديث موقعه الجغرافي أولاً'
        ], 400);
    }

    // الحصول على الطلبات المعلقة مع العلاقات المطلوبة
    $orders = Order::with([
        'user.profile', // تحميل بروفايل المستخدم
        'Order_Product.product.providerable.user.profile', // تحميل بروفايل التجار
        'coupons'
    ])
    ->where('status', 'pending')
    ->get();

    // معالجة البيانات وإضافة معلومات المسافة
    $processedOrders = $orders->map(function ($order) use ($driver) {
        try {
            // حساب المسافة بين السائق وصاحب الطلب
            $distanceToUser = $this->calculateDistance(
                $driver->lat,
                $driver->lang,
                $order->user->lat,
                $order->user->lang
            );
        } catch (\Exception $e) {
            // في حالة فشل حساب المسافة، نستخدم قيمة افتراضية كبيرة
            $distanceToUser = 9999;
        }

        // تجميع معلومات التجار مع المسافات
        $vendors = collect();
        $products = collect();

        foreach ($order->Order_Product as $orderProduct) {
            if ($orderProduct->product && $orderProduct->product->providerable) {
                $vendor = $orderProduct->product->providerable;

                try {
                    $distanceToVendor = $this->calculateDistance(
                        $driver->lat,
                        $driver->lang,
                        $vendor->user->lat,
                        $vendor->user->lang
                    );
                } catch (\Exception $e) {
                    // في حالة فشل حساب المسافة، نستخدم قيمة افتراضية كبيرة
                    $distanceToVendor = 9999;
                }

                $vendors->push([
                    'vendor_id' => $vendor->id,
                    'vendor_name' => $vendor->user->name,
                    'distance_to_driver' => $distanceToVendor,
                    'coordinates' => [
                        'lat' => $vendor->user->lat,
                        'address' => $vendor->user->profile->address ?? null,
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

        // فك تشفير بيانات توصيل التجار
        $firstVendorDeliveryData = $order->first_vendor_delivery_data ? json_decode($order->first_vendor_delivery_data, true) : null;
        $secondVendorDeliveryData = $order->second_vendor_delivery_data ? json_decode($order->second_vendor_delivery_data, true) : null;

        // الحصول على أسماء التجار من بيانات التوصيل
        if ($firstVendorDeliveryData) {
            $firstVendor = $vendors->firstWhere('vendor_id', $firstVendorDeliveryData['vendor_id']);
            $firstVendorDeliveryData['vendor_name'] = $firstVendor['vendor_name'] ?? null;
            $firstVendorDeliveryData['vendor_address'] = $firstVendor['coordinates']['address'] ?? null;
        }

        if ($secondVendorDeliveryData) {
            $secondVendor = $vendors->firstWhere('vendor_id', $secondVendorDeliveryData['vendor_id']);
            $secondVendorDeliveryData['vendor_name'] = $secondVendor['vendor_name'] ?? null;
            $secondVendorDeliveryData['vendor_address'] = $secondVendor['coordinates']['address'] ?? null;
        }

        return [
            'order_info' => [
                'order_id' => $order->id,
                'order_date' => $order->created_at,
                'total_price' => $order->total_price,
                'delivery_fee' => $order->delivery_fee,
                'final_price' => $order->total_price + $order->delivery_fee,
                'status' => $order->status,
                'note' => $order->note,
                'created_at' => $order->created_at,
                'coupons' => $coupons,
                // إضافة بيانات توصيل التجار
                'delivery_data' => [
                    'first_vendor' => $firstVendorDeliveryData,
                    'second_vendor' => $secondVendorDeliveryData
                ]
            ],
            'user_info' => [
                'user_id' => $order->user->id,
                'address' => $order->user->profile->address ?? null,
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

/**
 * حساب المسافة بين نقطتين باستخدام Google Maps Distance Matrix API
 * بإرجاع النتيجة بالكيلومتر
 */
private function calculateDistance($lat1, $lng1, $lat2, $lng2)
{
    $client = new \GuzzleHttp\Client();

    try {
        $response = $client->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'query' => [
                'origins' => "$lat1,$lng1",
                'destinations' => "$lat2,$lng2",
                'key' => env('GOOGLE_MAPS_API_KEY'),
                'units' => 'metric',
                'language' => 'ar'
            ],
            'timeout' => 10
        ]);

        $data = json_decode($response->getBody(), true);

        if ($data['status'] !== 'OK') {
            throw new \Exception('فشل في حساب المسافة: ' . ($data['error_message'] ?? ''));
        }

        if (empty($data['rows'][0]['elements'][0]['distance']['value'])) {
            throw new \Exception('لا توجد بيانات مسافة صالحة');
        }

        // إرجاع المسافة بالكيلومتر
        return $data['rows'][0]['elements'][0]['distance']['value'] / 1000;

    } catch (\Exception $e) {
        // في حالة حدوث خطأ، نلجأ إلى حساب المسافة باستخدام صيغة هافرساين كبديل
      //  return $this->calculateHaversineDistance($lat1, $lng1, $lat2, $lng2);
    }
}

/**
 * حساب المسافة باستخدام صيغة هافرساين كبديل عندما تفشل Google API
 */
private function calculateHaversineDistance($lat1, $lng1, $lat2, $lng2)
{
    $earthRadius = 6371; // نصف قطر الأرض بالكيلومترات

    // تحويل الدرجات إلى راديان
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);

    // حساب الفروقات
    $dLat = $lat2 - $lat1;
    $dLng = $lng2 - $lng1;

    // تطبيق صيغة هافرساين
    $a = sin($dLat/2) * sin($dLat/2) +
         cos($lat1) * cos($lat2) *
         sin($dLng/2) * sin($dLng/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    // حساب المسافة بالكيلومترات
    $distance = $earthRadius * $c;

    return round($distance, 2);
}

}
