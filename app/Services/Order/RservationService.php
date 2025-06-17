<?php

namespace App\Services\Order;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Order_Product;
use App\Models\Rseevation;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RservationService
{
    public function createOrder(array $validatedData)
    {DB::beginTransaction();

        try {
            $userId = Auth::id();
            $couponCode = $validatedData['coupon_code'] ?? null;
            $note = $validatedData['note'] ?? null;
            $coupon = null;
            $couponDiscount = 0;
            $originalPrice = 0;
            $finalPrice = 0;
            $couponApplied = false;
            $productDiscountApplied = false;
            $productDiscountValue = 0;
            $discountType = null;

            // التحقق من الكوبون إذا كان موجودًا
            if ($couponCode) {
                $coupon = Coupon::where('code', $couponCode)->first();

                if (!$coupon || !$coupon->isActive()) {
                    throw new \Exception('Coupon code is invalid or expired');
                }
            }

            // الحصول على المنتج مع معلومات الخصم
            $product = Product::with('discount')
                ->where('id', $validatedData['product_id'])
                ->lockForUpdate()
                ->first();

            if (!$product) {
                throw new ModelNotFoundException("Product not found: " . $validatedData['product_id']);
            }

            $originalPrice = $product->price;
            $finalPrice = $originalPrice;

            // تطبيق خصم المنتج إذا كان متاحًا
            if ($product->discount && $product->discount->isActive()) {
                $productDiscountApplied = true;
                $productDiscountValue = $product->discount->value;
                $discountType = "precentage"; // نسبة أو مبلغ ثابت
                $finalPrice = $product->discount->calculateDiscountedPrice($originalPrice);
            }

            $originalTotalPrice = $finalPrice;

            // تطبيق خصم الكوبون إذا كان متاحًا
            if ($coupon) {
                $couponDiscount = $finalPrice * ($coupon->discount_percent / 100);
                $finalPrice -= $couponDiscount;
                $couponApplied = true;
            }

            // إنشاء الحجز مع جميع معلومات الأسعار والخصومات
            $reservation = Rseevation::create([
                'user_id' => $userId,
                'product_id' => $validatedData['product_id'],
                'status' => 'pending',
                'original_price' => $originalPrice,
                'product_discount_applied' => $productDiscountApplied,
                'product_discount_value' => $productDiscountValue,
                'product_discount_type' => $discountType,
                'coupon_applied' => $couponApplied,
                'coupon_discount' => $couponApplied ? $couponDiscount : 0,
                'coupon_code' => $couponApplied ? $coupon->code : null,
                'note' =>$note,
                'total_price' => $finalPrice,
            ]);


            DB::commit();

            return [
                'success' => true,
                'reservation' => $reservation,
                'message' => 'Reservation created successfully'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to create reservation: ' . $e->getMessage()
            ];
        }
    }
    public function getAllReser()
    {
            return Rseevation::with(['product.images', 'user'])
                ->paginate(8); // تقسيم الطلبات إلى صفحات
    }

    public function getresersByStatus($status)
    {
            if ($status === 'all') {
                return $this->getAllReser(); // استدعاء الدالة التي تسترجع جميع الطلبات
            }

            return Rseevation::where('status', $status)
                ->with(['product.images', 'user'])
                ->paginate(8); // تقسيم الطلبات إلى صفحات
    }

    public function getReserByPriceRange($minPrice, $maxPrice)
    {
            $orders = Rseevation::whereBetween('total_price', [$minPrice, $maxPrice])
                ->with(['product.images', 'user'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return response()->json(['orders' => $orders], 200);
    }


    public function getreserByProduct($productId)
    {
            $orders = Rseevation::where('product_id', $productId)
                ->with(['product.images', 'user'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return $orders;
    }

    public function getreseByUser($userId)
    {
            $orders = Rseevation::where('user_id', $userId)
                ->with(['product.images', 'user'])
                ->paginate(8); // تحديد عدد الطلبات في كل صفحة (10 طلبات)

            return $orders;
    }





}
