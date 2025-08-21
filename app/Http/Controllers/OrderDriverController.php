<?php

namespace App\Http\Controllers;

use App\Events\PrivateNotification;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Order_Driver;
use App\Models\Order_Product;
use App\Models\Order_Product_Driver;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderDriverController extends Controller
{
    private function sendOrderStatusNotification($order, $status)
    {
        $statusMessages = [
            'pending' => 'طلبك رقم #'.$order->id.' قيد الانتظار',
            'accepted' => 'تم قبول طلبك رقم #'.$order->id.' وسيتم تجهيزه قريباً',
            'on_way' => 'طلبك رقم #'.$order->id.' في الطريق إليك',
            'complete' => 'تم تسليم طلبك رقم #'.$order->id.' بنجاح',
            'cancelled' => 'تم إلغاء طلبك رقم #'.$order->id,
            'partial_complete' => 'تم تسليم جزء من طلبك رقم #'.$order->id
        ];

        $message = $statusMessages[$status] ?? 'تم تحديث حالة طلبك رقم #'.$order->id.' إلى '.$status;

        // إرسال الإشعار الفوري
        event(new PrivateNotification($order->user_id, $message));

        // تخزين الإشعار في قاعدة البيانات
        UserNotification::create([
            'user_id' => $order->user_id,
            'notification' => $message,
        ]);
    }




    /**
 * إرسال إشعار للتاجر عند تغيير حالة منتجه
 */
    private function sendProviderNotification($orderProduct, $status)
    {
        $product = $orderProduct->product;
        $provider = $product->providerable;

        // التحقق من وجود مزود للمنتج
        if (!$provider) {
            Log::warning('No provider found for product: '.$product->id);
            return;
        }

        // جلب مستخدم المزود
        $providerUser = $provider->user;
        if (!$providerUser) {
            Log::warning('No user found for provider: '.$provider->id);
            return;
        }

        $statusMessages = [
            'pending' => 'طلب جديد للمنتج "'.$product->name.'" (طلب #'.$orderProduct->order_id.')',
            'on_way' => 'المنتج "'.$product->name.'" في الطريق للعميل (طلب #'.$orderProduct->order_id.')',
            'complete' => 'تم تسليم المنتج "'.$product->name.'" للعميل (طلب #'.$orderProduct->order_id.')',
            'cancelled' => 'تم إلغاء تسليم المنتج "'.$product->name.'" (طلب #'.$orderProduct->order_id.')'
        ];

        $message = $statusMessages[$status] ?? 'تم تحديث حالة المنتج "'.$product->name.'" إلى '.$status.' (طلب #'.$orderProduct->order_id.')';

        // إرسال الإشعار الفوري
        event(new PrivateNotification($providerUser->id, $message));

        // تخزين الإشعار في قاعدة البيانات
        UserNotification::create([
            'user_id' => $providerUser->id,
            'notification' => $message,
        ]);
    }




    private function sendVendorNotification($vendorUserId, $message)
    {
        // إرسال الإشعار الفوري
        event(new PrivateNotification($vendorUserId, $message));

        // تخزين الإشعار في قاعدة البيانات
        UserNotification::create([
            'user_id' => $vendorUserId,
            'notification' => $message,
        ]);
    }

    /**
     * قبول طلب المنتجات من قبل السائق
     */
    public function acceptOrderProducts(Request $request)
    {
        // التحقق من صلاحيات السائق
        $driver = Auth::user()->Driver;
        if (!$driver) {
            return response()->json(['message' => 'المستخدم ليس لديه صلاحيات سائق'], 403);
        }

        // التحقق من صحة البيانات
        $request->validate(['order_id' => 'required|exists:orders,id']);

        // جلب بيانات الطلب
        $order = Order::find($request->order_id);
        if ($order->status != 'pending') {
            return response()->json(['message' => 'الطلب ليس بحالة انتظار'], 400);
        }

        // جلب منتجات الطلب
        $orderProducts = Order_Product::where('order_id', $request->order_id)
            ->where('status', 'pending')
            ->get();

        if ($orderProducts->isEmpty()) {
            return response()->json(['message' => 'لا توجد منتجات قيد الانتظار في هذا الطلب'], 400);
        }

        DB::beginTransaction();
        try {
            // تحديث حالة الطلب
            $order->update(['status' => 'accepted']);

            // إرسال إشعار للمستخدم
            $this->sendOrderStatusNotification($order, 'accepted');

            // إنشاء سجل Order_Driver
            $orderDriver = Order_Driver::create([
                'order_id' => $request->order_id,
                'driver_id' => $driver->id,
                'status' => 'pending',
            ]);

            // إضافة منتجات الطلب للسائق
            $orderProductDrivers = $orderProducts->map(function ($product) use ($orderDriver) {
                return [
                    'order__driver_id' => $orderDriver->id,
                    'order__product_id' => $product->id,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            });

            Order_Product_Driver::insert($orderProductDrivers->toArray());

            DB::commit();

            foreach ($orderProducts as $orderProduct) {
                $this->sendProviderNotification($orderProduct, 'pending');
            }

            return response()->json([
                'message' => 'تم قبول الطلب بنجاح',
                'order' => $order,
                'order_driver' => $orderDriver,
                'accepted_products_count' => $orderProducts->count()
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'حدث خطأ أثناء معالجة الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }



















public function getDriverOrders(Request $request)
{
    $user = Auth::user();
    $driver = Auth::user();

    // التحقق من وجود إحداثيات السائق إذا كان المستخدم سائقاً
    if ($user->type != 1 && (empty($driver->lat) || empty($driver->lang))) {
        return response()->json([
            'success' => false,
            'message' => 'يجب على السائق تحديث موقعه الجغرافي أولاً'
        ], 400);
    }

    $query = Order_Driver::with([
            'order.user.profile',
            'order.Order_Product.product.providerable.user.profile',
            'order.Order_Product.product.images', // إضافة صور المنتجات
            'order.coupons',
            'Order_Product_Driver.Order_Product.product.providerable.user:id,name,lat,lang',
            'driver.user'
        ]);

    // فلترة حسب نوع المستخدم
    if ($user->type != 1) {
        $query->where('driver_id', $user->Driver->id);
    } else {
        $request->validate([
            'driver_id' => 'required|exists:drivers,id'
        ]);
        $query->where('driver_id', $request->driver_id);
    }

    // فلترة حسب الحالة
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    if ($request->has('order_driver_status')) {
        $query->where('status', $request->order_driver_status);
    }

    // تأكيد وجود المنتجات المرتبطة
    $query->whereHas('Order_Product_Driver.Order_Product.product');

    $driverOrders = $query->get();

    $formattedOrders = $driverOrders->map(function ($orderDriver) use ($driver) {
        $order = $orderDriver->order;

        // حساب المسافة بين السائق وصاحب الطلب
        try {
            $distanceToUser = $this->calculateDistance(
                $driver->lat,
                $driver->lang,
                $order->user->lat,
                $order->user->lang
            );
        } catch (\Exception $e) {
            $distanceToUser = 9999;
        }

        // تجميع معلومات التجار مع المسافات
        $vendors = collect();
        $products = collect();
        $orderProducts = collect(); // جمع معلومات Order_Product

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


                // جمع معلومات Order_Product الكاملة
                $orderProducts->push([
                    'order_product_id' => $orderProduct->id,
                    'product_id' => $orderProduct->product_id,
                    'order_id' => $orderProduct->order_id,
                    'quantity' => $orderProduct->quantity,
                    'status' => $orderProduct->status,
                    'original_price' => $orderProduct->original_price,
                    'unit_price' => $orderProduct->unit_price,
                    'total_price' => $orderProduct->total_price,
                    'discount_applied' => $orderProduct->discount_applied,
                    'discount_value' => $orderProduct->discount_value,
                    'discount_type' => $orderProduct->discount_type,
                    'created_at' => $orderProduct->created_at,
                    'updated_at' => $orderProduct->updated_at,

                    // معلومات المنتج الإضافية
                    'product' => [
                        'id' => $orderProduct->product->id,
                        'name' => $orderProduct->product->name,
                        'description' => $orderProduct->product->description,
                        'images' => $orderProduct->product->images->map(function($image) {
                            return [
                                'image_url' => $image->image_url,
                                'is_main' => $image->is_main
                            ];
                        }),
                        'category_id' => $orderProduct->product->category_id,
                        'providerable_type' => $orderProduct->product->providerable_type,
                        'providerable_id' => $orderProduct->product->providerable_id
                    ],

                    // معلومات التاجر
                    'vendor' => [
                        'id' => $vendor->id,
                        'name' => $vendor->user->name,
                        'phone' => $vendor->user->phone,
                        'address' => $vendor->user->profile->address ?? null,
                        'coordinates' => [
                            'lat' => $vendor->user->lat,
                            'lng' => $vendor->user->lang
                        ]
                    ],

                    // معلومات الخصم
                    'discount_info' => $orderProduct->hasDiscount() ? [
                        'has_discount' => true,
                        'discount_amount' => $orderProduct->discountAmount(),
                        'discount_type' => $orderProduct->discount_type,
                        'discount_value' => $orderProduct->discount_value
                    ] : [
                        'has_discount' => false,
                        'discount_amount' => 0
                    ]
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

        // بناء الهيكل المطلوب مشابهاً لدالة index
        $response = [
            'order_info' => [
                'order_id' => $order->id,
                'order_driver_id' => $orderDriver->id,
                'order_driver_status' => $orderDriver->status,
                'order_date' => $order->created_at,
                'total_price' => $order->total_price,
                'delivery_fee' => $order->delivery_fee,
                'final_price' => $order->total_price + $order->delivery_fee,
                'status' => $order->status,
                'note' => $order->note,
                'created_at' => $order->created_at,
                'coupons' => $coupons,
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
            'order_products' => $orderProducts, // إضافة معلومات Order_Product الكاملة
            'nearest_vendor_distance' => $vendors->min('distance_to_driver') ?? 0
        ];

        return $response;
    });

    // ترتيب الطلبات حسب الأقرب (أقرب تاجر في كل طلب)
    $sortedOrders = $formattedOrders->sortBy('nearest_vendor_distance')->values();

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
        'total_orders' => $sortedOrders->count(),
        'message' => 'Orders retrieved successfully'
    ], 200);
}












public function updateOrderProductStatus(Request $request)
{
    // التحقق من صحة البيانات
    $request->validate([
        'order_product_id' => 'required|exists:order__products,id',
        'status' => 'required|in:on_way,complete,cancelled'
    ]);

    // التحقق من صلاحيات السائق
    $driver = Auth::user()->Driver;
    if (!$driver) {
        return response()->json(['message' => 'المستخدم ليس لديه صلاحيات سائق'], 403);
    }

    // البحث عن سجل المنتج
    $orderProductDriver = Order_Product_Driver::whereHas('Order_Product', function($query) use ($request) {
            $query->where('id', $request->order_product_id);
        })
        ->whereHas('Order_Driver', function($query) use ($driver) {
            $query->where('driver_id', $driver->id);
        })
        ->with(['Order_Product.product.providerable.user', 'Order_Driver.order'])
        ->first();

    if (!$orderProductDriver) {
        return response()->json(['message' => 'المنتج غير موجود أو لا ينتمي لهذا السائق'], 404);
    }

    $orderProduct = $orderProductDriver->Order_Product;
    $orderDriver = $orderProductDriver->Order_Driver;
    $order = $orderDriver->order;
    $product = $orderProduct->product;
    $vendorUser = $product->providerable->user ?? null;

    DB::beginTransaction();
    try {
        // تحديث حالة المنتج حسب الحالة المطلوبة
        switch ($request->status) {
            case 'on_way':
                if ($orderProductDriver->status != 'pending') {
                    throw new \Exception('حالة المنتج يجب أن تكون pending لتغييرها إلى on_way');
                }
                $orderProductDriver->update(['status' => 'on_way']);
                $orderProduct->update(['status' => 'on_way']);

                // إرسال إشعار للمورد
               $this->sendProviderNotification($orderProduct, 'on_way');

                break;

            case 'complete':
                if ($orderProductDriver->status != 'on_way') {
                    throw new \Exception('حالة المنتج يجب أن تكون on_way لتغييرها إلى complete');
                }
                $orderProductDriver->update(['status' => 'complete']);
                $orderProduct->update(['status' => 'complete']);

                // إرسال إشعار للمورد
                 $this->sendProviderNotification($orderProduct, 'complete');

                break;

            case 'cancelled':
                $orderProductDriver->update(['status' => 'cancelled']);
                $orderProduct->update(['status' => 'cancelled']);

                 $this->sendProviderNotification($orderProduct, 'cancelled');

                // التحقق إذا كان هذا هو المنتج الوحيد للتاجر
                $this->handleCancelledProduct($order, $orderProduct);

                break;
        }

        // التحقق من حالة جميع منتجات الطلب
        $remainingProducts = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
            ->whereNotIn('status', ['complete', 'cancelled'])
            ->count();

        $canceledProducts = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
            ->where('status', 'cancelled')
            ->count();

        $totalProducts = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
            ->count();

        // تحديث حالة الطلب حسب حالة المنتجات
        if ($remainingProducts == 0) {
            if ($canceledProducts == 0) {
                $orderDriver->update(['status' => 'complete']);
                $order->update(['status' => 'complete']);
                $this->sendOrderStatusNotification($order, 'complete');
            }
            elseif ($canceledProducts == $totalProducts) {
                $orderDriver->update(['status' => 'cancelled']);
                $order->update(['status' => 'cancelled']);
                $this->sendOrderStatusNotification($order, 'cancelled');
            }
        }
        elseif (Order_Product_Driver::where('order__driver_id', $orderDriver->id)
            ->whereNotIn('status', ['on_way', 'cancelled'])
            ->doesntExist()) {
            $orderDriver->update(['status' => 'on_way']);
            $order->update(['status' => 'on_way']);
            $this->sendOrderStatusNotification($order, 'on_way');
        }

        DB::commit();

        return response()->json([
            'message' => 'تم تحديث حالة المنتج بنجاح',
            'order_status' => $order->fresh()->status,
            'order_driver_status' => $orderDriver->fresh()->status,
            'product_status' => $orderProductDriver->fresh()->status,
            'updated_delivery_fee' => $order->fresh()->delivery_fee
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'فشل في تحديث حالة المنتج',
            'error' => $e->getMessage()
        ], 400);
    }
}

// دالة جديدة للتعامل مع المنتج الملغي
private function handleCancelledProduct($order, $cancelledOrderProduct)
{
    // فك تشفير بيانات توصيل التجار
    $firstVendorData = $order->first_vendor_delivery_data
        ? json_decode($order->first_vendor_delivery_data, true)
        : null;

    $secondVendorData = $order->second_vendor_delivery_data
        ? json_decode($order->second_vendor_delivery_data, true)
        : null;

    // الحصول على التاجر الخاص بالمنتج الملغي
    $cancelledVendorId = $cancelledOrderProduct->product->providerable->id ?? null;

    if (!$cancelledVendorId) {
        return;
    }

    // التحقق إذا كان التاجر لديه منتجات أخرى غير ملغاة في الطلب
    $vendorActiveProducts = Order_Product::where('order_id', $order->id)
        ->whereHas('product', function($query) use ($cancelledVendorId) {
            $query->whereHas('providerable', function($q) use ($cancelledVendorId) {
                $q->where('id', $cancelledVendorId);
            });
        })
        ->where('status', '!=', 'cancelled')
        ->count();

    // إذا كان التاجر لا يزال لديه منتجات نشطة، لا داعي لتعديل التوصيل
    if ($vendorActiveProducts > 0) {
        return;
    }

    // تحديد أي بيانات تاجر تحتوي على الـ vendor_id الملغي
    $updatedFirstVendorData = $firstVendorData;
    $updatedSecondVendorData = $secondVendorData;
    $deliveryFeeReduction = 0;

    // التحقق من التاجر الأول
    if ($firstVendorData && isset($firstVendorData['vendor_id'])) {
        if ($firstVendorData['vendor_id'] == $cancelledVendorId) {
            $deliveryFeeReduction += $firstVendorData['delivery_fee'] ?? 0;
            $updatedFirstVendorData = null;
        }
    }

    // التحقق من التاجر الثاني
    if ($secondVendorData && isset($secondVendorData['vendor_id'])) {
        if ($secondVendorData['vendor_id'] == $cancelledVendorId) {
            $deliveryFeeReduction += $secondVendorData['delivery_fee'] ?? 0;
            $updatedSecondVendorData = null;
        }
    }

    // إذا تم إلغاء التاجر الثاني فقط، ننقل التاجر الأول إلى المركز الثاني إذا لزم الأمر
    if ($updatedFirstVendorData && !$updatedSecondVendorData) {
        $updatedSecondVendorData = $updatedFirstVendorData;
        $updatedFirstVendorData = null;
    }

    // تحديث سعر التوصيل الإجمالي
    $newDeliveryFee = max(0, $order->delivery_fee - $deliveryFeeReduction);

    // تحديث الطلب ببيانات التوصيل الجديدة
    $order->update([
        'delivery_fee' => $newDeliveryFee,
        'first_vendor_delivery_data' => $updatedFirstVendorData ? json_encode($updatedFirstVendorData) : null,
        'second_vendor_delivery_data' => $updatedSecondVendorData ? json_encode($updatedSecondVendorData) : null,
        'total_price' => $order->total_price - $deliveryFeeReduction
    ]);

    // تسجيل التغيير في السجل
    Log::info('تم تحديث تفاصيل التوصيل بعد إلغاء المنتج', [
        'order_id' => $order->id,
        'cancelled_product_id' => $cancelledOrderProduct->id,
        'cancelled_vendor_id' => $cancelledVendorId,
        'delivery_fee_reduction' => $deliveryFeeReduction,
        'new_delivery_fee' => $newDeliveryFee
    ]);
}














































    public function driverStatistics(Request $request)
    {
        $user = Auth::user();
        $driverId = null;

        // تحديد driver_id بناءً على نوع المستخدم
        if ($user->type == 1) { // إذا كان أدمن
            $request->validate([
                'driver_id' => 'required|exists:drivers,id'
            ]);
            $driverId = $request->driver_id;
        } else { // إذا كان سائق عادي
            if (!$user->Driver) {
                return response()->json(['message' => 'Driver not found'], 404);
            }
            $driverId = $user->Driver->id;
        }

        // الحصول على إحصائيات الطلبات
        $orderStats = Order_Driver::where('driver_id', $driverId)
            ->selectRaw('count(*) as total_orders')
            ->selectRaw('sum(case when status = "pending" then 1 else 0 end) as pending_orders')
            ->selectRaw('sum(case when status = "on_way" then 1 else 0 end) as on_way_orders')
            ->selectRaw('sum(case when status = "complete" then 1 else 0 end) as complete_orders')
            ->selectRaw('sum(case when status = "cancelled" then 1 else 0 end) as cancelled_orders')
            ->first();

        // حساب الأرباح من أجور التوصيل (الطلبات المكتملة فقط)
        $earnings = Order_Driver::where('driver_id', $driverId)
            ->where('order__drivers.status', 'complete') // تحديد الجدول بشكل صريح
            ->join('orders', 'order__drivers.order_id', '=', 'orders.id')
            ->sum('orders.delivery_fee');

        // الحصول على معلومات السائق الأساسية
        $driver = Driver::find($driverId);

        return response()->json([
            'statistics' => [
                'total_orders' => $orderStats->total_orders ?? 0,
                'pending_orders' => $orderStats->pending_orders ?? 0,
                'on_way_orders' => $orderStats->on_way_orders ?? 0,
                'complete_orders' => $orderStats->complete_orders ?? 0,
                'cancelled_orders' => $orderStats->cancelled_orders ?? 0,
            ],
            'earnings' => $earnings,
            'driver_info' => [
                'driver_id' => $driverId,
                'driver_name' => $driver->user->name ?? 'Unknown Driver',
                'phone' => $driver->user->phone ?? null,
                'email' => $driver->user->email ?? null
            ]
        ], 200);
    }

    protected function getUpdatedOrderDetails($orderDriverId, $driverId)
    {
        return Order_Driver::with([
                'order:id,user_id,status,created_at',
                'Order_Product_Driver.Order_Product.product:id,name'
            ])
            ->where('id', $orderDriverId)
            ->where('driver_id', $driverId)
            ->first();
    }

    public function showDriverOrder(Request $request, $orderId)
    {
        $user = Auth::user();
        $query = Order_Driver::with([
                'order:id,user_id,status,created_at,delivery_fee,total_price',
                'Order_Product_Driver.Order_Product.product.providerable.user:id,name,lat,lang',
                'driver.user'
            ])
            ->where('order_id', $orderId);

        // إذا كان المستخدم ليس أدمن (type != 1) يرى فقط طلباته
        if ($user->type != 1) {
            $query->where('driver_id', $user->Driver->id);
        }
        // إذا كان أدمن (type == 1) يجب إرسال driver_id
        else {
            $request->validate([
                'driver_id' => 'required|exists:drivers,id'
            ]);
            $query->where('driver_id', $request->driver_id);
        }

        $orderDriver = $query->first();

        if (!$orderDriver) {
            return response()->json(['message' => 'Order not found or access denied'], 404);
        }

        // تجميع البيانات
        $productsData = $orderDriver->Order_Product_Driver->map(function ($productDriver) {
            $orderProduct = $productDriver->Order_Product;
            $product = $orderProduct->product;
            $vendor = $product->providerable;
            $vendorUser = $vendor->user;

            return [
                'product_info' => [
                    'order_product_id' => $orderProduct->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'total_price' => $orderProduct->total_price,
                    'quantity' => $orderProduct->quantity,
                    'status' => $orderProduct->status,
                    'created_at' => $orderProduct->created_at
                ],
                'vendor_info' => [
                    'vendor_id' => $vendor->id,
                    'vendor_name' => $vendorUser->name ?? 'Unknown Vendor',
                    'lat' => $vendorUser->lat ?? null,
                    'lang' => $vendorUser->lang ?? null,
                ]
            ];
        });

        $response = [
            'order_id' => $orderDriver->order_id,
            'order_driver_id' => $orderDriver->id,
            'order_driver_status' => $orderDriver->status,
            'order_status' => $orderDriver->order->status,
            'total_price' => $orderDriver->order->total_price,
            'delivery_fee' => $orderDriver->order->delivery_fee,
            'order_created_at' => $orderDriver->order->created_at,
            'products' => $productsData
        ];

        // إضافة معلومات السائق للأدمن
        if ($user->type == 1 && isset($orderDriver->driver)) {
            $response['driver_info'] = [
                'driver_id' => $orderDriver->driver->id,
                'driver_name' => $orderDriver->driver->user->name ?? 'Unknown Driver',
                'driver_phone' => $orderDriver->driver->user->phone ?? null,
                'driver_lat' => $orderDriver->driver->user->lat ?? null,
                'driver_lang' => $orderDriver->driver->user->lang ?? null
            ];
        }

        return response()->json(['order' => $response], 200);
    }

    public function showOrder(Request $request, $orderId)
    {
        $user = Auth::user();

        // جلب الطلب مع العلاقات المطلوبة
        $order = Order::with([
                'Order_Driver.driver.user:id,name,phone,lat,lang',
                'Order_Product.product.providerable.user:id,name'
            ])
            ->where('id', $orderId)
            ->first();

        // التحقق من وجود الطلب
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // التحقق من أن المستخدم هو صاحب الطلب
        if ($order->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized access to this order'], 403);
        }

        // إذا كانت حالة الطلب pending
        if ($order->status == 'pending') {
            return response()->json([
                'message' => 'No driver has accepted your order yet',
                'order' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'total_price' => $order->total_price,
                    'delivery_fee' => $order->delivery_fee,
                    'created_at' => $order->created_at
                ]
            ], 200);
        }

        // تجميع بيانات المنتجات
        $products = $order->Order_Product->map(function ($orderProduct) {
            return [
                'product_id' => $orderProduct->product_id,
                'name' => $orderProduct->product->name,
                'quantity' => $orderProduct->quantity,
                'price' => $orderProduct->price,
                'status' => $orderProduct->status
            ];
        });

        // تجميع بيانات السائقين إذا وجدوا
        $drivers = [];
        if ($order->Order_Driver->isNotEmpty()) {
            $drivers = $order->Order_Driver->map(function ($orderDriver) {
                return [
                    'driver_id' => $orderDriver->driver_id,
                    'driver_name' => $orderDriver->driver->user->name ?? 'Unknown',
                    'phone' => $orderDriver->driver->user->phone ?? null,
                    'status' => $orderDriver->status,
                    'location' => [
                        'lat' => $orderDriver->driver->user->lat ?? null,
                        'lang' => $orderDriver->driver->user->lang ?? null
                    ]
                ];
            });
        }

        // إعداد الاستجابة النهائية
        $response = [
            'order_id' => $order->id,
            'status' => $order->status,
            'total_price' => $order->total_price,
            'delivery_fee' => $order->delivery_fee,
            'created_at' => $order->created_at,
            'products' => $products,
            'drivers' => $drivers
        ];

        return response()->json(['order' => $response], 200);
    }
}
