<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Product;
use App\Models\Order_Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Exceptions\InsufficientProductQuantityException;
class OrderService
{
     public function createOrder(array $validatedData)
    {
            // Start database transaction
            DB::beginTransaction();

            try {
                $userId = Auth::id();

                // Create the order
                $order = Order::create([
                    'user_id' => $userId,
                    'total_price' => 0, // Will be updated later
                    'status' => 'pending',
                ]);

                $totalPrice = 0;

                foreach ($validatedData['products'] as $productData) {
                    // Find the product with lock for update to prevent race conditions
                    $product = Product::where('id', $productData['product_id'])
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

                    // Calculate product total price
                    $productTotalPrice = $product->price * $productData['quantity'];

                    // Create order product
                    Order_Product::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $productData['quantity'],
                        'total_price' => $productTotalPrice,
                    ]);

                    $totalPrice += $productTotalPrice;
                }

                // Update order total price
                $order->update(['total_price' => $totalPrice]);

                // Commit transaction if everything is successful
                DB::commit();

                return $order;

            } catch (\Exception $e) {
                // Rollback transaction on error
                DB::rollBack();

                // Re-throw the exception for the controller to handle
                throw $e;
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
