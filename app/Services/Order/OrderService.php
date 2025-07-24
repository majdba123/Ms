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
use App\Models\Driver_Price;
use App\Models\Provider_Product;

class OrderService
{


   /* public function createOrder(array $validatedData)
    {
        DB::beginTransaction();

        try {
            $user = Auth::user(); // الحصول على بيانات المستخدم

            // التحقق من وجود الإحداثيات الجغرافية للمستخدم
            if (empty($user->lat) || empty($user->lang)) {
                throw new \Exception('يجب عليك إضافة موقعك الجغرافي (خط الطول وخط العرض) قبل إنشاء الطلب');
            }

            $userId = Auth::id();
            $couponCode = $validatedData['coupon_code'] ?? null;
            $note = $validatedData['note'] ?? null;
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

            // التحقق من عدد التجار المختلفين
            $productIds = collect($validatedData['products'])->pluck('product_id')->unique();
            $providersCount = Product::with('providerable')
                ->whereIn('id', $productIds)
                ->get()
                ->groupBy(function($product) {
                    return $product->providerable_type . '-' . $product->providerable_id;
                })
                ->count();

            if ($providersCount > 2) {
                throw new \Exception('You cannot order products from more than 2 different providers in a single order');
            }

            $order = Order::create([
                'user_id' => $userId,
                'total_price' => 0,
                'status' => 'pending',
                'note' => $note,
            ]);

            $totalPrice = 0;
            $orderProductsDetails = [];

            foreach ($validatedData['products'] as $productData) {
                $product = Product::with('discount', 'images')
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
                    'images' => $product->images->map(function($image) {
                        return $image->imag;
                    })->toArray()
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
    }*/



public function createOrder(array $validatedData)
{
    DB::beginTransaction();

    try {
        $user = Auth::user();

        // التحقق من وجود إحداثيات المستخدم
        if (empty($user->lat) || empty($user->lang)) {
            throw new \Exception('يجب عليك إضافة موقعك الجغرافي قبل إنشاء الطلب');
        }

        $userId = Auth::id();
        $couponCode = $validatedData['coupon_code'] ?? null;
        $note = $validatedData['note'] ?? null;
        $coupon = null;
        $couponDiscount = 0;
        $originalTotalPrice = 0;
        $couponApplied = false;

        if ($couponCode) {
            $coupon = Coupon::where('code', $couponCode)->first();
            if (!$coupon || !$coupon->isActive()) {
                throw new \Exception('كود الكوبون غير صالح أو منتهي الصلاحية');
            }
        }

        // الحصول على جميع المنتجات مع مزوديها
        $products = Product::with(['providerable.user', 'discount', 'images'])
            ->whereIn('id', collect($validatedData['products'])->pluck('product_id'))
            ->get();

        // حساب المسافات لكل مزود
        $vendors = [];
        foreach ($products as $product) {
            $provider = $product->providerable;
            if (!$provider instanceof Provider_Product) continue;

            $vendorId = $provider->id;
            if (!isset($vendors[$vendorId])) {
                $distance = $this->calculateDistance(
                    $user->lat,
                    $user->lang,
                    $provider->user->lat,
                    $provider->user->lang
                );

                $vendors[$vendorId] = [
                    'distance' => $distance,
                    'provider' => $provider
                ];
            }
        }

        if (count($vendors) > 2) {
            throw new \Exception('لا يمكن الطلب من أكثر من تاجرين مختلفين');
        }

        // تحديد التاجر الأقرب والأبعد
        usort($vendors, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        $nearestVendor = $vendors[0];
        $furthestVendor = count($vendors) > 1 ? $vendors[1] : null;

        // حساب المسافة من التاجر الأقرب إلى التاجر الأبعد
        $nearestToFurthestDistance = 0;
        if ($furthestVendor) {
            $nearestToFurthestDistance = $this->calculateDistance(
                $nearestVendor['provider']->user->lat,
                $nearestVendor['provider']->user->lang,
                $furthestVendor['provider']->user->lat,
                $furthestVendor['provider']->user->lang
            );
        }

        // حساب سعر التوصيل الإجمالي
        $deliveryFee = $this->getDeliveryPrice($nearestVendor['distance']);

        if ($furthestVendor) {
            $deliveryFee += $this->getDeliveryPrice($nearestToFurthestDistance);
        }

        // إنشاء الطلب
        $order = Order::create([
            'user_id' => $userId,
            'total_price' => 0,
            'delivery_fee' => $deliveryFee,
            'status' => 'pending',
            'note' => $note,
        ]);

        // معالجة المنتجات
        $totalPrice = 0;
        foreach ($validatedData['products'] as $productData) {
            $product = $products->firstWhere('id', $productData['product_id']);
            if (!$product) {
                throw new \Exception('المنتج غير موجود: ' . $productData['product_id']);
            }

            $originalPrice = $product->price;
            $discountApplied = false;
            $discountValue = 0;
            $discountType = "percentage";
            $productPrice = $originalPrice;

            if ($product->discount && $product->discount->isActive()) {
                $discountApplied = true;
                $discountValue = $product->discount->value;
                $productPrice = $product->discount->calculateDiscountedPrice($originalPrice);
            }

            Order_Product::create([
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

            $totalPrice += $productPrice * $productData['quantity'];
        }

        // تطبيق خصم الكوبون
        $originalTotalPrice = $totalPrice;
        if ($coupon) {
            $couponDiscount = $totalPrice * ($coupon->discount_percent / 100);
            $totalPrice -= $couponDiscount;
            $couponApplied = true;

            $order->coupons()->attach($coupon, [
                'discount_amount' => $couponDiscount
            ]);
        }

        // تحديث السعر النهائي
        $order->update([
            'total_price' => $totalPrice + $deliveryFee,
            'original_price' => $originalTotalPrice,
            'coupon_applied' => $couponApplied,
            'coupon_discount' => $couponDiscount,
            'coupon_code' => $coupon ? $coupon->code : null,
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'total_price' => $order->total_price,
                'products_price' => $totalPrice,
                'delivery_fee' => $deliveryFee,
                'distances' => [
                    'user_to_nearest' => [
                        'distance_km' => round($nearestVendor['distance'], 2),
                        'delivery_fee' => $this->getDeliveryPrice($nearestVendor['distance'])
                    ],
                    'nearest_to_furthest' => $furthestVendor ? [
                        'distance_km' => round($nearestToFurthestDistance, 2),
                        'delivery_fee' => $this->getDeliveryPrice($nearestToFurthestDistance)
                    ] : null
                ],
                'vendors_info' => [
                    'nearest' => [
                        'id' => $nearestVendor['provider']->id,
                        'name' => $nearestVendor['provider']->user->name
                    ],
                    'furthest' => $furthestVendor ? [
                        'id' => $furthestVendor['provider']->id,
                        'name' => $furthestVendor['provider']->user->name
                    ] : null
                ]
            ],
            'message' => 'تم إنشاء الطلب بنجاح'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'فشل في إنشاء الطلب: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * حساب سعر التوصيل بناءً على المسافة
 */
private function getDeliveryPrice($distance)
{
    // إذا كانت المسافة صفر، لا توجد تكلفة توصيل
    if ($distance <= 0) {
        return 0;
    }

    // الحصول على النطاق المناسب من جدول الأسعار
    $priceRange = Driver_Price::where('from_distance', '<=', $distance)
        ->where('to_distance', '>=', $distance)
        ->first();

    // إذا لم يتم العثور على نطاق، نستخدم آخر نطاق للمسافات الكبيرة
    if (!$priceRange) {
        $priceRange = Driver_Price::orderBy('to_distance', 'desc')->first();
        if (!$priceRange) {
            return 0;
        }

        // إذا كانت المسافة أكبر من النطاق الأقصى، نحسب عدد النطاقات المطلوبة
        if ($distance > $priceRange->to_distance) {
            $rangeSize = $priceRange->to_distance - $priceRange->from_distance;
            if ($rangeSize > 0) {
                $numberOfRanges = ceil($distance / $rangeSize);
                return $priceRange->price * $numberOfRanges;
            }
        }
    }

    return $priceRange ? $priceRange->price : 0;
}

    /**
     * حساب المسافة بين موقعين باستخدام Google Maps API
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $client = new \GuzzleHttp\Client();
        $response = $client->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'query' => [
                'origins' => "$lat1,$lng1",
                'destinations' => "$lat2,$lng2",
                'key' => env('GOOGLE_MAPS_API_KEY'),
                'units' => 'metric',
                'language' => 'ar'
            ],
            'timeout' => 10
        ]);

        $data = json_decode($response->getBody(), true);

        if ($data['status'] !== 'OK') {
            throw new \Exception('فشل في حساب المسافة: ' . ($data['error_message'] ?? ''));
        }

        if (empty($data['rows'][0]['elements'][0]['distance']['value'])) {
            throw new \Exception('لا توجد بيانات مسافة صالحة');
        }

        return $data['rows'][0]['elements'][0]['distance']['value'] / 1000;
    }















































































public function cancelOrder($orderId)
{
    DB::beginTransaction();

    try {
        $userId = Auth::id();

        // البحث عن الطلب مع التحقق من ملكية المستخدم له
        $order = Order::where('id', $orderId)
                    ->where('user_id', $userId)
                    ->with(['Order_Product.product']) // استخدام الاسم الصحيح للعلاقة
                    ->first();

        if (!$order) {
            throw new \Exception('Order not found or you are not authorized to cancel this order');
        }

        if ($order->status === 'cancelled') {
            throw new \Exception('Order is already cancelled');
        }

        // تحديث حالة الطلب
        $order->update(['status' => 'cancelled']);

        // إرجاع الكميات للمنتجات وتحديث حالة منتجات الطلب
        foreach ($order->Order_Product as $orderProduct) { // استخدام Order_Product بدلاً من products
            // تحديث حالة منتج الطلب
            $orderProduct->update(['status' => 'cancelled']);

            // إرجاع الكمية للمنتج الأصلي إذا كان له كمية
            if ($orderProduct->product->quantity !== null) {
                $orderProduct->product->increment('quantity', $orderProduct->quantity);
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Order has been cancelled successfully',
            'order_id' => $order->id,
            'new_status' => 'cancelled'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Failed to cancel order: ' . $e->getMessage(),
        ], 500);
    }
}
    public function getOrdersByPriceRange($minPrice, $maxPrice)
    {
        $orders = Order::whereBetween('total_price', [$minPrice, $maxPrice])
            ->with(['Order_Product.product.images', 'coupons'])
            ->paginate(8);

        return $this->formatOrdersResponse($orders);
    }

    public function getAllOrders()
    {
        $orders = Order::with(['Order_Product.product.images', 'coupons'])
            ->paginate(8);

        return $this->formatOrdersResponse($orders);
    }

    public function getOrdersByStatus($status)
    {
        if ($status === 'all') {
            return $this->getAllOrders();
        }

        $orders = Order::where('status', $status)
            ->with(['Order_Product.product.images', 'coupons'])
            ->paginate(8);

        return $this->formatOrdersResponse($orders);
    }

    public function getOrdersByProduct($productId)
    {
        $orderProducts = Order_Product::where('product_id', $productId)
            ->with(['order.coupons', 'order.Order_Product.product.images', 'product.images'])
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
            ->with(['Order_Product.product.images', 'coupons'])
            ->paginate(8);

        return $this->formatOrdersResponse($orders);
    }

    public function getOrdersByCategory($categoryId)
    {
        $products = Product::byCategory($categoryId)->pluck('id');
        $orderProducts = Order_Product::whereIn('product_id', $products)
            ->with(['order.coupons', 'order.Order_Product.product.images', 'product.images'])
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
                'images' => $product->images->map(function($image) {
                    return $image->imag;
                })->toArray()
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
            'note' => $order->note,
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
