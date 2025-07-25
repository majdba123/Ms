<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Order;
use App\Models\Order_Driver;
use App\Models\Order_Product;
use App\Models\Order_Product_Driver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderDriverController extends Controller
{


public function acceptOrderProducts(Request $request)
{
    // التحقق من وجود سجل Driver للمستخدم المصادق عليه
    $driver = Auth::user()->driver;

    if (!$driver) {
        return response()->json([
            'message' => 'المستخدم ليس لديه صلاحيات سائق'
        ], 403);
    }

    $request->validate([
        'order_id' => 'required|exists:orders,id',
    ]);

    // التحقق من أن الطلب بحالة pending
    $order = Order::find($request->order_id);

    if ($order->status != 'pending') {
        return response()->json([
            'message' => 'الطلب ليس بحالة pending'
        ], 400);
    }

    // جلب جميع منتجات الطلب التي بحالة pending
    $orderProducts = Order_Product::where('order_id', $request->order_id)
        ->where('status', 'pending')
        ->get();

    if ($orderProducts->isEmpty()) {
        return response()->json([
            'message' => 'لا توجد منتجات pending في هذا الطلب'
        ], 400);
    }

    // بدء المعاملة
    DB::beginTransaction();

    try {
        // تحديث حالة الطلب إلى accepted
        $order->update(['status' => 'accepted']);

        // إنشاء سجل Order_Driver
        $orderDriver = Order_Driver::create([
            'order_id' => $request->order_id,
            'driver_id' => $driver->id, // استخدام $driver->id بدلاً من $driver مباشرة
            'status' => 'pending',
        ]);

        // إنشاء سجلات Order_Product_Driver لكل المنتجات
        $orderProductDrivers = [];
        foreach ($orderProducts as $product) {
            $orderProductDrivers[] = [
                'order__driver_id' => $orderDriver->id,
                'order__product_id' => $product->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        Order_Product_Driver::insert($orderProductDrivers);

        DB::commit();

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
    $query = Order_Driver::with([
            'order:id,user_id,status,created_at,delivery_fee,total_price',
            'Order_Product_Driver.Order_Product.product.providerable.user:id,name,lat,lang',
            'driver.user'
        ]);

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

    // فلترة حسب حالة الطلب إذا موجودة
    if ($request->has('status')) {
        $query->whereHas('order', function($q) use ($request) {
            $q->where('status', $request->status);
        });
    }

    // فلترة حسب حالة order_driver إذا موجودة
    if ($request->has('order_driver_status')) {
        $query->where('status', $request->order_driver_status);
    }

    $driverOrders = $query->get();

    // تجميع البيانات
    $formattedOrders = $driverOrders->map(function ($orderDriver) use ($user) {
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

        return $response;
    });

    return response()->json(['orders' => $formattedOrders], 200);
}




    public function updateOrderProductStatus(Request $request)
    {
        $request->validate([
            'order_product_id' => 'required|exists:order__products,id',
            'status' => 'required|in:on_way,complete,cancelled'
        ]);

        $driver = Auth::user()->Driver;
        if (!$driver) {
            return response()->json(['message' => 'المستخدم ليس لديه صلاحيات سائق'], 403);
        }

        // البحث عن سجل Order_Product_Driver الخاص بالسائق الحالي والمنتج المطلوب
        $orderProductDriver = Order_Product_Driver::whereHas('Order_Product', function($query) use ($request) {
                $query->where('id', $request->order_product_id);
            })
            ->whereHas('Order_Driver', function($query) use ($driver) {
                $query->where('driver_id', $driver->id);
            })
            ->with(['Order_Product', 'Order_Driver.order'])
            ->first();

        if (!$orderProductDriver) {
            return response()->json(['message' => 'Order product not found or does not belong to this driver'], 404);
        }

        $orderProduct = $orderProductDriver->Order_Product;
        $orderDriver = $orderProductDriver->Order_Driver;
        $order = $orderDriver->order;

        DB::beginTransaction();
        try {
            switch ($request->status) {
                case 'on_way':
                    if ($orderProductDriver->status != 'pending') {
                        throw new \Exception('Product status must be pending to change to on_way');
                    }
                    $orderProductDriver->update(['status' => 'on_way']);
                    $orderProduct->update(['status' => 'on_way']);
                    break;

                case 'complete':
                    if ($orderProductDriver->status != 'on_way') {
                        throw new \Exception('Product status must be on_way to change to complete');
                    }
                    $orderProductDriver->update(['status' => 'complete']);
                    $orderProduct->update(['status' => 'complete']);
                    break;

                case 'cancelled':
                // تحديث حالة المنتج إلى cancel
                $orderProductDriver->update(['status' => 'cancelled']);
                $orderProduct->update(['status' => 'cancelled']);

                // التحقق إذا كانت جميع منتجات الطلب ملغية
                $allCanceled = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
                    ->where('status', '!=', 'cancelled')
                    ->doesntExist();

                if ($allCanceled) {
                    $orderDriver->update(['status' => 'cancelled']);
                    $order->update(['status' => 'cancelled']);
                }
                    break;
            }

            // التحقق من حالة جميع المنتجات
            $remainingProducts = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
                ->whereNotIn('status', ['complete', 'cancelled'])
                ->count();

            $canceledProducts = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
                ->where('status', 'cancelled')
                ->count();

            $totalProducts = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
                ->count();

            // إذا كانت جميع المنتجات إما complete أو cancel
            if ($remainingProducts == 0) {
                // إذا كان كلها complete
                if ($canceledProducts == 0) {
                    $orderDriver->update(['status' => 'complete']);
                    $order->update(['status' => 'complete']);
                }
                // إذا كان كلها cancel
                elseif ($canceledProducts == $totalProducts) {
                    $orderDriver->update(['status' => 'cancelled']);
                    $order->update(['status' => 'cancelled']);
                }
                // إذا كان مزيج من complete و cancel
                else {
                    $orderDriver->update(['status' => 'partial_complete']);
                    $order->update(['status' => 'partial_complete']);
                }
            }
            // إذا كانت جميع المنتجات on_way أو cancel
            elseif (Order_Product_Driver::where('order__driver_id', $orderDriver->id)
                ->whereNotIn('status', ['on_way', 'cancelled'])
                ->doesntExist()) {
                $orderDriver->update(['status' => 'on_way']);
                $order->update(['status' => 'on_way']);
            }

            DB::commit();

            return response()->json([
                'message' => 'Order product status updated successfully',
                'order_status' => $order->fresh()->status,
                'order_driver_status' => $orderDriver->fresh()->status,
                'product_status' => $orderProductDriver->fresh()->status
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update order product status',
                'error' => $e->getMessage()
            ], 400);
        }
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
