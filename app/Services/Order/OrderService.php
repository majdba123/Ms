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
use App\Models\Order_Product_Driver;
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

        // التحقق من الكوبون
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
                    'provider' => $provider,
                    'coordinates' => [
                        'lat' => $provider->user->lat,
                        'lng' => $provider->user->lang
                    ]
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

        // حساب جميع المسافات المطلوبة
        $nearestToFurthestDistance = 0;
        $userToFurthestDistance = 0;
        $totalDistance = 0;
        $deliveryFee = 0;
        $calculationNote = '';

        // حساب أسعار التوصيل للتجار وتخزينها مع الـ ID
        $firstVendorDeliveryData = null;
        $secondVendorDeliveryData = null;

        // المسافة الثابتة: من المستخدم إلى التاجر الأقرب
        $userToNearestDistance = $nearestVendor['distance'];
        $totalDistance = $userToNearestDistance;
        $deliveryFee = $this->getDeliveryPrice($userToNearestDistance);

        // تخزين بيانات التاجر الأول مع سعر التوصيل
        $firstVendorDeliveryData = json_encode([
            'vendor_id' => $nearestVendor['provider']->id,
            'delivery_fee' => $this->getDeliveryPrice($userToNearestDistance),
            'distance_km' => round($userToNearestDistance, 2)
        ]);

        if ($furthestVendor) {
            // المسافة من الأقرب إلى الأبعد
            $nearestToFurthestDistance = $this->calculateDistance(
                $nearestVendor['coordinates']['lat'],
                $nearestVendor['coordinates']['lng'],
                $furthestVendor['coordinates']['lat'],
                $furthestVendor['coordinates']['lng']
            );

            // المسافة من المستخدم إلى الأبعد
            $userToFurthestDistance = $this->calculateDistance(
                $user->lat,
                $user->lang,
                $furthestVendor['coordinates']['lat'],
                $furthestVendor['coordinates']['lng']
            );

            // نأخذ الأقل بين المسافتين
            $minDistance = min($userToFurthestDistance, $nearestToFurthestDistance);
            $totalDistance += $minDistance;
            $deliveryFee += $this->getDeliveryPrice($minDistance);

            // تخزين بيانات التاجر الثاني مع سعر التوصيل
            $secondVendorDeliveryData = json_encode([
                'vendor_id' => $furthestVendor['provider']->id,
                'delivery_fee' => $this->getDeliveryPrice($minDistance),
                'distance_km' => round($minDistance, 2),
                'route_type' => $userToFurthestDistance <= $nearestToFurthestDistance ?
                               'user_to_furthest' : 'nearest_to_furthest'
            ]);

            $calculationNote = sprintf(
                "حساب التوصيل: %s كم (منك إلى الأقرب) + %s كم (الأقل بين [منك إلى الأبعد (%s كم) أو من الأقرب إلى الأبعد (%s كم)])",
                round($userToNearestDistance, 2),
                round($minDistance, 2),
                round($userToFurthestDistance, 2),
                round($nearestToFurthestDistance, 2)
            );
        } else {
            $calculationNote = sprintf(
                "حساب التوصيل: %s كم (منك إلى التاجر الوحيد)",
                round($userToNearestDistance, 2)
            );
        }

        // إنشاء الطلب
        $order = Order::create([
            'user_id' => $userId,
            'total_price' => 0,
            'delivery_fee' => $deliveryFee,
            'first_vendor_delivery_data' => $firstVendorDeliveryData,
            'second_vendor_delivery_data' => $secondVendorDeliveryData,
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

        // إعداد تفاصيل المسافات للإرجاع
        $distanceDetails = [
            'user_to_nearest' => [
                'distance_km' => round($userToNearestDistance, 2),
                'price' => $this->getDeliveryPrice($userToNearestDistance),
                'vendor_id' => $nearestVendor['provider']->id,
                'vendor_name' => $nearestVendor['provider']->user->name,
                'coordinates' => [
                    'user' => ['lat' => $user->lat, 'lng' => $user->lang],
                    'vendor' => $nearestVendor['coordinates']
                ]
            ],
            'user_to_furthest' => $furthestVendor ? [
                'distance_km' => round($userToFurthestDistance, 2),
                'price' => $this->getDeliveryPrice($userToFurthestDistance),
                'coordinates' => [
                    'user' => ['lat' => $user->lat, 'lng' => $user->lang],
                    'vendor' => $furthestVendor['coordinates']
                ]
            ] : null,
            'nearest_to_furthest' => $furthestVendor ? [
                'distance_km' => round($nearestToFurthestDistance, 2),
                'price' => $this->getDeliveryPrice($nearestToFurthestDistance),
                'vendor_id' => $furthestVendor['provider']->id,
                'vendor_name' => $furthestVendor['provider']->user->name,
                'coordinates' => [
                    'start' => $nearestVendor['coordinates'],
                    'end' => $furthestVendor['coordinates']
                ]
            ] : null
        ];

        // تحضير البيانات للإرجاع
        $firstVendorData = json_decode($firstVendorDeliveryData, true);
        $secondVendorData = $secondVendorDeliveryData ? json_decode($secondVendorDeliveryData, true) : null;

        return response()->json([
            'success' => true,
            'order' => [
                'id' => $order->id,
                'total_price' => $order->total_price,
                'products_price' => $totalPrice,
                'delivery_fee' => $deliveryFee,
                'first_vendor_delivery_data' => $firstVendorData,
                'second_vendor_delivery_data' => $secondVendorData,
                'distance_details' => $distanceDetails,
                'delivery_calculation' => [
                    'total_distance' => round($totalDistance, 2),
                    'calculation_note' => $calculationNote,
                    'selected_route' => $furthestVendor ? [
                        'user_to_nearest' => round($userToNearestDistance, 2),
                        'additional_leg' => round(min($userToFurthestDistance, $nearestToFurthestDistance), 2),
                        'chosen_route' => $userToFurthestDistance <= $nearestToFurthestDistance ?
                                          'user_to_furthest' : 'nearest_to_furthest'
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
    // الحصول على النطاق المناسب من جدول الأسعار
    $priceRange = Driver_Price::where('from_distance', '<=', $distance)
        ->where('to_distance', '>=', $distance)
        ->first();

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

        // التحقق من أن الطلب في حالة pending
        if ($order->status !== 'pending') {
            throw new \Exception('Cannot cancel order. Order status is: ' . $order->status);
        }

        // التحقق من أن جميع منتجات الطلب في حالة pending
        $nonPendingProducts = $order->Order_Product->where('status', '!=', 'pending');

        if ($nonPendingProducts->count() > 0) {
            throw new \Exception('Cannot cancel order. Some products are already being processed');
        }

        // تحديث حالة الطلب
        $order->update(['status' => 'cancelled']);

        // تحديث حالة منتجات الطلب
        foreach ($order->Order_Product as $orderProduct) {
            // تحديث حالة منتج الطلب
            $orderProduct->update(['status' => 'cancelled']);

            // إرجاع الكمية للمنتج الأصلي إذا كان له كمية
            if ($orderProduct->product->quantity !== null) {
                $orderProduct->product->increment('quantity', $orderProduct->quantity);
            }
        }

        // إذا كان هناك سائق مرتبط بالطلب، نقوم بتحديث حالة Order_Driver أيضًا
        if ($order->Order_Driver()->exists()) {
            $order->Order_Driver()->update(['status' => 'cancelled']);

            // أيضًا تحديث Order_Product_Driver إذا كان موجودًا
            Order_Product_Driver::whereHas('Order_Driver', function($query) use ($orderId) {
                $query->where('order_id', $orderId);
            })->update(['status' => 'cancelled']);
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
