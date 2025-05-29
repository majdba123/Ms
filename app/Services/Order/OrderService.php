<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Product;
use App\Models\Order_Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Exceptions\InsufficientProductQuantityException;
use App\Models\Coupon;

class OrderService
{
    public function createOrder(array $validatedData)
    {
        DB::beginTransaction();

        try {
            $userId = Auth::id();
            $couponCode = $validatedData['coupon_code'] ?? null;
            $coupon = null;
            $couponDiscount = 0;
            $originalTotalPrice = 0;
            $couponApplied = false;

            // التحقق من صحة الكوبون إذا تم تقديمه
            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->first();

                if (!$coupon || !$coupon->isActive()) {
                    throw new \Exception('كود الخصم غير صالح أو منتهي الصلاحية');
                }
            }

            // إنشاء الطلب
            $order = Order::create([
                'user_id' => $userId,
                'total_price' => 0,
                'status' => 'pending',
            ]);

            $totalPrice = 0;
            $orderProductsDetails = [];

            foreach ($validatedData['products'] as $productData) {
                // Find the product with lock for update to prevent race conditions
            $product = Product::with('discount')
                ->where('id', $productData['product_id'])
                ->where(function($query) {
                    $query->whereNull('quantity')
                        ->orWhere('quantity', '>', 0);
                })
                ->lockForUpdate()
                ->first();

                if (!$product) {
                    throw new ModelNotFoundException("Product not found or out of stock: " . $productData['product_id']);
                }

                // Check if product has quantity (not a service)
                if ($product->quantity !== null) {
                    // Validate requested quantity
                    if ($productData['quantity'] > $product->quantity) {
                        return response()->json(['message' => 'quantity not available'], 404);
                    }

                    // Reduce the product quantity
                    $product->decrement('quantity', $productData['quantity']);
                }

                $originalPrice = $product->price;
                $discountApplied = false;
                $discountValue = 0;
                $discountType = null;
                $productPrice = $originalPrice;

                // تطبيق خصم المنتج المباشر إذا كان موجوداً وفعالاً
                if ($product->discount && $product->discount->isActive()) {
                    $discountApplied = true;
                    $discountValue = $product->discount->value;
                    $productPrice = $product->discount->calculateDiscountedPrice($originalPrice);
                }

                $orderProduct = Order_Product::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $productData['quantity'],
                    'total_price' => $productPrice * $productData['quantity'],
                    'status' => 'pending',
                ]);

                $totalPrice += $orderProduct->total_price;

                $orderProductsDetails[] = [
                    'id' => $orderProduct->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $productData['quantity'],
                    'original_unit_price' => $originalPrice,
                    'final_unit_price' => $productPrice,
                    'discount_applied' => $discountApplied,
                    'discount_value' => $discountValue,
                    'total_price' => $productPrice * $productData['quantity'],
                ];
            }

            // حفظ السعر الأصلي قبل تطبيق الكوبون
            $originalTotalPrice = $totalPrice;

            // تطبيق خصم الكوبون إذا كان صالحاً
            if ($coupon) {
                $couponDiscount = $totalPrice * ($coupon->discount_percent / 100);
                $totalPrice -= $couponDiscount;
                $couponApplied = true;

                // ربط الكوبون بالطلب
                $order->coupons()->attach($coupon, [
                    'discount_amount' => $couponDiscount
                ]);
            }

            $order->update(['total_price' => $totalPrice]);

            $responseData = [
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'user_id' => $order->user_id,
                    'original_total_price' => $originalTotalPrice,
                    'total_price' => $order->total_price,
                    'coupon_applied' => $couponApplied,
                    'coupon_discount' => $couponDiscount,
                    'coupon_code' => $coupon ? $coupon->code : null,
                    'coupon_discount_percent' => $coupon ? $coupon->discount_percent : null,
                    'status' => $order->status,
                    'created_at' => $order->created_at,
                    'products' => $orderProductsDetails,
                ],
                'message' => 'تم إنشاء الطلب بنجاح',
            ];

            DB::commit();

            return response()->json($responseData);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء الطلب: ' . $e->getMessage(),
            ], 500);
        }
    }



        public function getOrdersByPriceRange($minPrice, $maxPrice)
        {
            $orders = Order::whereBetween('total_price', [$minPrice, $maxPrice])
                ->with(['order_product:id,order_id,product_id', 'order_product.product:id,name'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return response()->json(['orders' => $orders], 200);
        }

        public function getAllOrders()
        {
            return Order::with(['order_product:id,order_id,product_id', 'order_product.product:id,name'])
                ->paginate(8); // تقسيم الطلبات إلى صفحات
        }



        public function getOrdersByStatus($status)
        {
            if ($status === 'all') {
                return $this->getAllOrders(); // استدعاء الدالة التي تسترجع جميع الطلبات
            }

            return Order::where('status', $status)
                ->with(['order_product:id,order_id,product_id', 'order_product.product:id,name'])
                ->paginate(8); // تقسيم الطلبات إلى صفحات
        }




        public function getOrdersByProduct($productId)
        {
            $orders = Order_Product::where('product_id', $productId)
                ->with(['order:id,status,total_price,user_id', 'order.user:id,name,email'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return $orders;
        }

        public function getOrdersByUser($userId)
        {
            $orders = Order::where('user_id', $userId)
                ->with(['order_product:id,order_id,product_id,status,total_price', 'order_product.product:id,name,price'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return $orders;
        }

        public function getOrdersByCategory($categoryId)
        {
            $products = Product::byCategory($categoryId)->pluck('id');

            $orders = Order_Product::whereIn('product_id', $products)
                ->with(['order:id,status,user_id,total_price', 'order.user:id,name,email'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return $orders;
        }






}
