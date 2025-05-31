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

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();

            if (!$coupon || !$coupon->isActive()) {
                throw new \Exception('Coupon code is invalid or expired');
            }
        }

        $order = Order::create([
            'user_id' => $userId,
            'total_price' => 0,
            'status' => 'pending',
        ]);

        $totalPrice = 0;
        $orderProductsDetails = [];

        foreach ($validatedData['products'] as $productData) {
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

            if ($product->quantity !== null) {
                if ($productData['quantity'] > $product->quantity) {
                    return response()->json(['message' => 'Quantity not available'], 404);
                }
                $product->decrement('quantity', $productData['quantity']);
            }

            $originalPrice = $product->price;
            $discountApplied = false;
            $discountValue = 0;
            $discountType = "precentage";
            $productPrice = $originalPrice;

            if ($product->discount && $product->discount->isActive()) {
                $discountApplied = true;
                $discountValue = $product->discount->value;
                $productPrice = $product->discount->calculateDiscountedPrice($originalPrice);
            }

            $orderProduct = Order_Product::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $productData['quantity'],
                'original_price' => $originalPrice,
                'unit_price' => $productPrice,
                'total_price' => $productPrice * $productData['quantity'],
                'discount_applied' => $discountApplied,
                'discount_value' => $discountValue,
                'discount_type' => $discountType,
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
                'discount_type' => $discountType,
                'total_price' => $productPrice * $productData['quantity'],
            ];
        }

        $originalTotalPrice = $totalPrice;

        if ($coupon) {
            $couponDiscount = $totalPrice * ($coupon->discount_percent / 100);
            $totalPrice -= $couponDiscount;
            $couponApplied = true;

            $order->coupons()->attach($coupon, [
                'discount_amount' => $couponDiscount
            ]);
        }

        $order->update(['total_price' => $totalPrice]);

        $responseData = [
            'success' => true,
            'order' => $this->formatOrderDetails($order, $orderProductsDetails, $originalTotalPrice, $couponApplied, $couponDiscount, $coupon),
            'message' => 'Order created successfully',
        ];

        DB::commit();
        return response()->json($responseData);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Failed to create order: ' . $e->getMessage(),
        ], 500);
    }
}
    public function getOrdersByPriceRange($minPrice, $maxPrice)
    {
        $orders = Order::whereBetween('total_price', [$minPrice, $maxPrice])
            ->with(['Order_Product.product', 'coupons'])
            ->paginate(8);

        return $this->formatOrdersResponse($orders);
    }

    public function getAllOrders()
    {
        $orders = Order::with(['Order_Product.product', 'coupons'])
            ->paginate(8);

        return $this->formatOrdersResponse($orders);
    }

    public function getOrdersByStatus($status)
    {
        if ($status === 'all') {
            return $this->getAllOrders();
        }

        $orders = Order::where('status', $status)
            ->with(['Order_Product.product', 'coupons'])
            ->paginate(8);

        return $this->formatOrdersResponse($orders);
    }

    public function getOrdersByProduct($productId)
    {
        $orderProducts = Order_Product::where('product_id', $productId)
            ->with(['order.coupons', 'order.Order_Product.product', 'product'])
            ->paginate(8);

        $formattedOrders = [];
        foreach ($orderProducts as $orderProduct) {
            $order = $orderProduct->order;
            $formattedOrders[] = $this->formatSingleOrder($order);
        }

        return response()->json([
            'success' => true,
            'orders' => $formattedOrders,
            'pagination' => [
                'total' => $orderProducts->total(),
                'per_page' => $orderProducts->perPage(),
                'current_page' => $orderProducts->currentPage(),
                'last_page' => $orderProducts->lastPage(),
            ]
        ]);
    }

    public function getOrdersByUser($userId)
    {
        $orders = Order::where('user_id', $userId)
            ->with(['Order_Product.product', 'coupons'])
            ->paginate(8);

        return $this->formatOrdersResponse($orders);
    }

    public function getOrdersByCategory($categoryId)
    {
        $products = Product::byCategory($categoryId)->pluck('id');
        $orderProducts = Order_Product::whereIn('product_id', $products)
            ->with(['order.coupons', 'order.Order_Product.product', 'product'])
            ->paginate(8);

        $formattedOrders = [];
        foreach ($orderProducts as $orderProduct) {
            $order = $orderProduct->order;
            $formattedOrders[] = $this->formatSingleOrder($order);
        }

        return response()->json([
            'success' => true,
            'orders' => $formattedOrders,
            'pagination' => [
                'total' => $orderProducts->total(),
                'per_page' => $orderProducts->perPage(),
                'current_page' => $orderProducts->currentPage(),
                'last_page' => $orderProducts->lastPage(),
            ]
        ]);
    }

    private function formatOrdersResponse($orders)
    {
        $formattedOrders = [];
        foreach ($orders as $order) {
            $formattedOrders[] = $this->formatSingleOrder($order);
        }

        return response()->json([
            'success' => true,
            'orders' => $formattedOrders,
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ]
        ]);
    }

    private function formatSingleOrder(Order $order)
    {
        $coupon = $order->coupons->first();
        $originalTotalPrice = $order->Order_Product->sum(function($orderProduct) {
            return $orderProduct->quantity * $orderProduct->product->price;
        });

        $couponApplied = $coupon ? true : false;
        $couponDiscount = $coupon ? $originalTotalPrice * ($coupon->discount_percent / 100) : 0;

        $products = [];
        foreach ($order->Order_Product as $orderProduct) {
            $product = $orderProduct->product;
            $originalPrice = $product->price;
            $discountApplied = $product->discount && $product->discount->isActive();
            $finalPrice = $discountApplied ? $product->discount->calculateDiscountedPrice($originalPrice) : $originalPrice;

            $products[] = [
                'id' => $orderProduct->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $orderProduct->quantity,
                'original_unit_price' => $originalPrice,
                'final_unit_price' => $finalPrice,
                'discount_applied' => $discountApplied,
                'discount_value' => $discountApplied ? $product->discount->value : 0,
                'total_price' => $finalPrice * $orderProduct->quantity,
                'status' => $orderProduct->status,
            ];
        }

        return [
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
            'products' => $products,
        ];
    }

    private function formatOrderDetails($order, $orderProductsDetails, $originalTotalPrice, $couponApplied, $couponDiscount, $coupon)
    {
        return [
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
        ];
    }
}
