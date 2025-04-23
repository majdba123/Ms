<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\Order\CreateOrderRequest;
use App\Models\Order;
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
        $order = $this->orderService->createOrder($request->validated());

        return response()->json($order, 201);
    }

    public function getUserOrders(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:all,pending,complete,cancelled',
          ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->first();
            return response()->json(['error' => $errors], 422);
        }

        $user = Auth::user();
        $status = $request->status;

        if ($status === 'all') {
            $orders = Order::where('user_id', $user->id)->get();
        } else {
            $orders = Order::where('user_id', $user->id)
                            ->where('status', $status)
                            ->get();
        }

        return response()->json(['orders' => $orders], 200);
    }


    public function getProductOrder($order_id)
    {
        $user = Auth::user();

        // جلب الطلب والتحقق من أن المستخدم هو صاحب الطلب
        $order = Order::where('id', $order_id)
                      ->where('user_id', $user->id)
                      ->with('Order_Product.product')
                      ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found or you do not have permission to view this order'], 404);
        }

        return response()->json(['order' => $order], 200);
    }

}
