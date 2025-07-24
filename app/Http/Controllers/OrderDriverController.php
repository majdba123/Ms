<?php

namespace App\Http\Controllers;

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






public function getDriverOrders()
{
    $driverId = Auth::user()->Driver->id;

    // الحصول على جميع طلبات السائق مع العلاقات المطلوبة
    $driverOrders = Order_Driver::with([
            'order:id,user_id,status,created_at,delivery_fee,total_price',
            'Order_Product_Driver.Order_Product.product.providerable.user:id,name,lat,lang',
        ])
        ->where('driver_id', $driverId)
        ->get();

    // تجميع البيانات بالشكل المطلوب
    $formattedOrders = $driverOrders->map(function ($orderDriver) {
        // الحصول على جميع المنتجات في هذا الطلب للسائق
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

        return [
            'order_id' => $orderDriver->order_id,
            'order_driver_id' => $orderDriver->id,
            'order_status' => $orderDriver->order->status,
            'total_price' => $orderDriver->order->total_price,
            'delivery_fee' => $orderDriver->order->delivery_fee,
            'order_created_at' => $orderDriver->order->created_at,
            'products' => $productsData
        ];
    });

    return response()->json(['orders' => $formattedOrders], 200);
}






    public function updateOrderProductStatus(Request $request)
    {
        $request->validate([
            'order_product_id' => 'required|exists:order__products,id',
            'status' => 'required|in:on_way,complete,cancel'
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

                case 'cancel':
                    $orderProductDriver->update(['status' => 'cancel']);
                    $orderProduct->update(['status' => 'cancel']);
                    break;
            }

            // التحقق من حالة جميع المنتجات
            $remainingProducts = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
                ->whereNotIn('status', ['complete', 'cancel'])
                ->count();

            $canceledProducts = Order_Product_Driver::where('order__driver_id', $orderDriver->id)
                ->where('status', 'cancel')
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
                    $orderDriver->update(['status' => 'cancel']);
                    $order->update(['status' => 'cancel']);
                }
                // إذا كان مزيج من complete و cancel
                else {
                    $orderDriver->update(['status' => 'partial_complete']);
                    $order->update(['status' => 'partial_complete']);
                }
            }
            // إذا كانت جميع المنتجات on_way أو cancel
            elseif (Order_Product_Driver::where('order__driver_id', $orderDriver->id)
                ->whereNotIn('status', ['on_way', 'cancel'])
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
}
