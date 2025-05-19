<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\Product;
use App\Models\Order_Product;
use App\Models\Rseevation;

use Illuminate\Support\Facades\Auth;

class RservationService
{
    public function createOrder(array $validatedData)
    {
        $userId = Auth::id();

        // Get the product with its price
        $product = Product::findOrFail($validatedData['product_id']);

        // Create the reservation with the product price
        $reservation = Rseevation::create([
            'user_id' => $userId,
            'product_id' => $validatedData['product_id'],
            'status' => 'pending',
            'total_price' => $product->price // Store the product price
        ]);

        return $reservation;
    }


    public function getAllReser()
    {
            return Rseevation::with(['product', 'user'])
                ->paginate(8); // تقسيم الطلبات إلى صفحات
    }

    public function getresersByStatus($status)
    {
            if ($status === 'all') {
                return $this->getAllReser(); // استدعاء الدالة التي تسترجع جميع الطلبات
            }

            return Rseevation::where('status', $status)
                ->with(['product', 'user'])
                ->paginate(8); // تقسيم الطلبات إلى صفحات
    }

    public function getReserByPriceRange($minPrice, $maxPrice)
    {
            $orders = Rseevation::whereBetween('total_price', [$minPrice, $maxPrice])
                ->with(['product', 'user'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return response()->json(['orders' => $orders], 200);
    }


    public function getreserByProduct($productId)
    {
            $orders = Rseevation::where('product_id', $productId)
                ->with(['product', 'user'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return $orders;
    }

    public function getreseByUser($userId)
    {
            $orders = Rseevation::where('user_id', $userId)
                ->with(['product', 'user'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return $orders;
    }





}
