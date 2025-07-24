<?php

namespace App\Http\Controllers;

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
        $driver = Auth::user()->Driver->id;
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'order_product_ids' => 'required|array',
            'order_product_ids.*' => 'exists:order__products,id',
        ]);

        // التحقق من أن جميع order_products تنتمي إلى order المحدد
        $invalidProducts = Order_Product::whereIn('id', $request->order_product_ids)
            ->where('order_id', '!=', $request->order_id)
            ->exists();

        if ($invalidProducts) {
            return response()->json([
                'message' => 'بعض المنتجات لا تنتمي إلى الطلب المحدد'
            ], 400);
        }

        // التحقق من أن جميع order_products بحالة pending
        $notPendingProducts = Order_Product::whereIn('id', $request->order_product_ids)
            ->where('status', '!=', 'pending')
            ->exists();

        if ($notPendingProducts) {
            return response()->json([
                'message' => 'بعض المنتجات ليست بحالة pending'
            ], 400);
        }

        // التحقق من أن جميع المنتجات تنتمي لنفس التاجر
        $vendorIds = Order_Product::whereIn('id', $request->order_product_ids)
            ->with('product.providerable')
            ->get()
            ->map(function($orderProduct) {
                return $orderProduct->product->providerable->id ?? null;
            })
            ->unique()
            ->filter()
            ->values();

        if ($vendorIds->count() > 1) {
            return response()->json([
                'message' => 'المنتجات المحددة تنتمي إلى أكثر من تاجر'
            ], 400);
        }

        // بدء المعاملة
        DB::beginTransaction();

        try {
            // إنشاء سجل Order_Driver
            $orderDriver = Order_Driver::create([
                'order_id' => $request->order_id,
                'driver_id' => $driver,
                'status' => 'pending',
            ]);

            // تحديث حالة order_products إلى accepted
            Order_Product::whereIn('id', $request->order_product_ids)
                ->update(['status' => 'accepted']);

            // إنشاء سجلات Order_Product_Driver
            $orderProductDrivers = [];
            foreach ($request->order_product_ids as $productId) {
                $orderProductDrivers[] = [
                    'order__driver_id' => $orderDriver->id,
                    'order__product_id' => $productId,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            Order_Product_Driver::insert($orderProductDrivers);

            DB::commit();

            return response()->json([
                'message' => 'تم قبول الطلب بنجاح',
                'order_driver' => $orderDriver
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
                'order:id,user_id,status,created_at',
                'Order_Product_Driver.Order_Product.product.providerable.user:id,name'
            ])
            ->where('driver_id', $driverId)
            ->get();

        // تجميع البيانات بالشكل المطلوب
        $formattedOrders = $driverOrders->groupBy('order_id')->map(function ($orderDrivers) {
            // تجميع المنتجات حسب التاجر داخل كل طلب
            return $orderDrivers->flatMap(function ($orderDriver) {
                return $orderDriver->Order_Product_Driver->groupBy(function ($item) {
                    return $item->Order_Product->product->providerable->id;
                })->map(function ($products, $vendorId) use ($orderDriver) {
                    $firstProduct = $products->first();
                    $vendor = $firstProduct->Order_Product->product->providerable;

                    return [
                        'order_id' => $orderDriver->order_id,
                        'order_driver_id' => $orderDriver->id,
                        'order_details' => $orderDriver->order,
                        'vendor' => [
                            'id' => $vendor->id,
                            'name' => $vendor->user->name ?? 'Unknown Vendor',
                        ],
                        'products' => $products->map(function ($productDriver) {
                            $orderProduct = $productDriver->Order_Product;
                            return [
                                'order_product_id' => $orderProduct->id,
                                'product_id' => $orderProduct->product_id,
                                'product_name' => $orderProduct->product->name,
                                'total_price' => $orderProduct->total_price,
                                'quantity' => $orderProduct->quantity,
                                'status' => $orderProduct->status,
                                'created_at' => $orderProduct->created_at
                            ];
                        })
                    ];
                });
            });
        })->collapse()->values();

        return response()->json(['orders' => $formattedOrders], 200);
    }


    public function updateOrderToOnWay(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'order_driver_id' => 'required|exists:order__drivers,id',
            ]);

            // Get authenticated driver
            $driver = Auth::user()->Driver;

            // Start transaction
            DB::beginTransaction();

            // Verify order belongs to driver and lock for update
            $orderDriver = Order_Driver::with(['Order_Product_Driver.Order_Product'])
                ->where('id', $validated['order_driver_id'])
                ->where('driver_id', $driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Get all related order product drivers
            $orderProductDrivers = $orderDriver->Order_Product_Driver;

            // Prepare IDs for bulk update
            $orderProductIds = $orderProductDrivers->pluck('order__product_id')->toArray();

            // Perform bulk updates
            $this->updateOrderStatuses(
                $orderDriver->id,
                $orderProductIds,
                $orderDriver->status
            );

            // Commit transaction
            DB::commit();

            // Reload the order with fresh data
            $updatedOrder = $this->getUpdatedOrderDetails($validated['order_driver_id'], $driver->id);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated to "on_way" successfully',
                'data' => [
                    'order' => $updatedOrder,
                    'metrics' => [
                        'products_updated' => count($orderProductIds),
                        'driver_products_updated' => $orderProductDrivers->count(),
                    ]
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or does not belong to this driver',
                'error_code' => 'ORDER_NOT_FOUND'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'error_code' => 'VALIDATION_ERROR'
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order status update failed: ' . $e->getMessage(), [
                'order_driver_id' => $request->order_driver_id ?? null,
                'driver_id' => $driver->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status',
                'error' => config('app.debug') ? $e->getMessage() : null,
                'error_code' => 'SERVER_ERROR'
            ], 500);
        }
    }

    /**
     * Update all related order statuses
     *
     * @param int $orderDriverId
     * @param array $orderProductIds
     * @param string $currentStatus
     */
    protected function updateOrderStatuses($orderDriverId, $orderProductIds, $currentStatus)
    {
        // Validate current status
        if ($currentStatus !== 'pending') {
            throw new \Exception('Order must be in pending status to be updated');
        }

        // Update order driver status
        Order_Driver::where('id', $orderDriverId)
            ->update([
                'status' => 'on_way',
                'updated_at' => now()
            ]);

        // Update related order product drivers
        Order_Product_Driver::where('order__driver_id', $orderDriverId)
            ->update([
                'status' => 'on_way',
                'updated_at' => now()
            ]);

        // Update related order products
        Order_Product::whereIn('id', $orderProductIds)
            ->update([
                'status' => 'on_way',
                'updated_at' => now()
            ]);
    }

    /**
     * Get updated order details
     *
     * @param int $orderDriverId
     * @param int $driverId
     * @return \App\Models\Order_Driver
     */
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
