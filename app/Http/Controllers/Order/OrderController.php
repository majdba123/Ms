<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\Order\CreateOrderRequest;
use App\Services\Order\OrderService;

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
}
