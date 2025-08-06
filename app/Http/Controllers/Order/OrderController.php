<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Order\CreateOrderRequest;
use App\Models\Order;
use App\Models\Order_Product;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function createOrder(CreateOrderRequest $request)
    {
        return $this->orderService->createOrder($request->validated());
    }


    public function cancelOrder($order_id)
    {
        return $this->orderService->cancelOrder($order_id);
    }


  public function getUserOrders(Request $request)
{
    $validator = Validator::make($request->all(), [
        'status' => 'required|string|in:all,pending,complete,cancelled',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first()
        ], 422);
    }

    $user = Auth::user();
    $status = $request->status;

    $orders = Order::where('user_id', $user->id)
                ->when($status !== 'all', fn($q) => $q->where('status', $status))
                ->with([
                    'Order_Product.product.discount',
                    'Order_Product.product.images',
                    'coupons'
                ])
                ->get();

    $formattedOrders = $orders->map(function($order) {
        $formatted = [
            'order_id' => $order->id,
            'note' => $order->note,
            'delivery_fee' => $order->delivery_fee,
            'status' => $order->status,
            'created_at' => $order->created_at,
            'products' => $order->Order_Product->map(function($orderProduct) {
                return [
                    'order_product_id' => $orderProduct->id,
                    'product_id' => $orderProduct->product_id,
                    'product_name' => $orderProduct->product->name,
                    'total_price' => $orderProduct->total_price,
                    'quantity' => $orderProduct->quantity,
                    'status' => $orderProduct->status,
                    'product_images' => $orderProduct->product->images->map(function($image) {
                        return [
                            'image_id' => $image->id,
                            'image_url' => $image->imag
                        ];
                    }),
                    'discount' => $orderProduct->product->discount ?? null
                ];
            }),
            'coupons' => $order->coupons
        ];

        return $formatted;
    });

    return response()->json([
        'success' => true,
        'orders' => $formattedOrders,
        'message' => 'Orders retrieved successfully'
    ]);
}
    public function getProductOrder($order_id)
    {
        $user = Auth::user();
        $order = Order::with(['Order_Product.product.discount', 'coupons'])
                     ->where('id', $order_id)
                     ->where('user_id', $user->id)
                     ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or you do not have permission to view this order'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'order' => $this->formatOrder($order),
            'message' => 'Order retrieved successfully'
        ]);
    }

    private function formatOrder(Order $order)
    {
        $originalTotal = $order->Order_Product->sum(function($op) {
            return $op->product->price * $op->quantity;
        });

        $coupon = $order->coupons->first();
        $couponApplied = $coupon !== null;
        $couponDiscount = $couponApplied ? $originalTotal * ($coupon->discount_percent / 100) : 0;

        $products = $order->Order_Product->map(function($op) {
            $product = $op->product;
            $hasProductDiscount = $product->discount && $product->discount->isActive();
            $finalPrice = $hasProductDiscount
                ? $product->discount->calculateDiscountedPrice($product->price)
                : $product->price;

            return [
                'id' => $op->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $op->quantity,
                'original_unit_price' => $product->price,
                'final_unit_price' => $finalPrice,
                'discount_applied' => $hasProductDiscount,
                'discount_value' => $hasProductDiscount ? $product->discount->value : 0,
                'total_price' => $finalPrice * $op->quantity,
                'status' => $op->status,
            ];
        });

        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'original_total_price' => $originalTotal,
            'total_price' => $order->total_price,
            'coupon_applied' => $couponApplied,
            'coupon_discount' => $couponDiscount,
            'coupon_code' => $couponApplied ? $coupon->code : null,
            'coupon_discount_percent' => $couponApplied ? $coupon->discount_percent : null,
            'status' => $order->status,
            'created_at' => $order->created_at,
            'products' => $products,
        ];
    }
}
