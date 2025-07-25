<?php

namespace App\Services\Product;

use App\Models\FoodType;
use App\Models\Product;
use App\Models\Provider_Product;
use App\Models\Provider_Service;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProductService
{
   public function createProduct(array $data, $providerType = null): Product
    {
        if ($providerType === null) {
            throw new \InvalidArgumentException('Provider type must be specified');
        }

        $providerTypeClass = $providerType == 1
            ? 'App\\Models\\Provider_Service'
            : 'App\\Models\\Provider_Product';

        $productData = [
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => $data['price'],
            'quantity' => $providerType == 1 ? null : ($data['quantity'] ?? null),
            'time_of_service' => $providerType == 1 ? ($data['time_of_service'] ?? null) : null,
            'category_id' => $data['category_id'],
            'providerable_id' => $data['provider_id'],
            'providerable_type' => $providerTypeClass,
        ];

        // إذا كان المستخدم food_provider
        if (auth()->user()->type == 'food_provider' && $providerType == 0) {
            $foodType = FoodType::find($data['food_type_id']);

            if (!$foodType) {
                throw new \InvalidArgumentException('Invalid food type ID');
            }


            $productData['food_type'] = $foodType->title; // تخزين العنوان بدلاً من ID
        }

        return Product::create($productData);
    }

    public function updateProduct(array $data, Product $product): Product
    {
        $updateData = [
            'name' => $data['name'] ?? $product->name,
            'description' => $data['description'] ?? $product->description,
            'price' => $data['price'] ?? $product->price,
            'category_id' => $data['category_id'] ?? $product->category_id,
        ];

        if (isset($data['quantity']) || $product->providerable_type !== 'App\\Models\\Provider_Service') {
            $updateData['quantity'] = $data['quantity'] ?? $product->quantity;
        }

        if (isset($data['time_of_service']) || $product->providerable_type === 'App\\Models\\Provider_Service') {
            $updateData['time_of_service'] = $data['time_of_service'] ?? $product->time_of_service;
        }

        // تحديث نوع الطعام إذا كان المستخدم food_provider والمنتج ليس خدمة
        if (auth()->user()->type == 'food_provider' && $product->providerable_type === 'App\\Models\\Provider_Product') {
            if (isset($data['food_type_id'])) {
                $foodType = FoodType::find($data['food_type_id']);

                if (!$foodType) {
                    throw new \InvalidArgumentException('Invalid food type ID');
                }



                $updateData['food_type'] = $foodType->title; // تحديث العنوان
            }
        }

        $product->update($updateData);
        return $product->fresh();
    }
    public function deleteProduct($id): array
    {
        $product = Product::find($id);

        if (!$product) {
            return ['message' => 'Product not found', 'status' => 404];
        }

        $user = Auth::user();
        $provider = $product->providerable;

        if (!$provider || $provider->user_id != $user->id) {
            return ['message' => 'Unauthorized', 'status' => 403];
        }

        $product->delete();
        return ['message' => 'Product deleted successfully', 'status' => 200];
    }

    public function formatProductResponse(Product $product): array
    {
        $discountInfo = null;

        // Check if product has an active discount
        if ($product->discount && $product->discount->isActive()) {
            $discountInfo = [
                'has_discount' => true,
                'discount_value' => $product->discount->value,
                'discount_type' => $product->discount->type,
                'original_price' => $product->price,
                'final_price' => $product->discount->calculateDiscountedPrice($product->price),
                'discount_start_date' => $product->discount->fromtime,
                'discount_end_date' => $product->discount->totime,
            ];
        } else {
            $discountInfo = [
                'has_discount' => false,
                'original_price' => $product->price,
                'final_price' => $product->price,
            ];
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'category_id' => $product->category_id,
            'providerable_type' => $product->providerable_type,
            'providerable_id' => $product->providerable_id,
            'quantity' => $product->quantity,
            'food_type' => $product->food_type,
            'time_of_service' => $product->time_of_service,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
            'images' => $product->images->map(function($image) {
                return ['url' => $image->imag];
            }),
            'rating' => $this->formatRatingResponse($product->rating),
            'discount_info' => $discountInfo,
        ];
    }

    protected function formatRatingResponse($ratings)
    {
        if (!$ratings) return null;

        return $ratings->map(function($rating) {
            return [
                'id' => $rating->id,
                'num' => $rating->num,
                'comment' => $rating->comment,
                'created_at' => $rating->created_at,
                'user' => [
                    'id' => $rating->user->id ?? null,
                    'name' => $rating->user->name ?? null,
                    'image' => $rating->user->profile->image ?? null
                ],
                'answers' => $rating->answer_rating ?? []
            ];
        });
    }

    public function getProductsByType($providerType, $request = null, $perPage = 10)
    {
        $query = Product::with(['images', 'category', 'discount', 'rating'])
            ->orderBy('created_at', 'desc');

        if ($providerType == 0) {
            $query->where('providerable_type', 'App\\Models\\Provider_Product');

            if ($request && $request->is('api/user*')) {
                $query->where(function($q) {
                    $q->whereNull('quantity')
                    ->orWhere('quantity', '>', 0);
                });
            }
        } else {
            $query->where('providerable_type', 'App\\Models\\Provider_Service');
        }

        $products = $query->paginate($perPage);

        return [
            'data' => $products->map(function($product) {
                return $this->formatProductResponse($product);
            }),
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ];
    }


    /*public function getProductsByCategory($categoryId, $request = null, $perPage = 10)
    {
        $query = Product::with(['images', 'category', 'discount', 'rating'])
            ->where('category_id', $categoryId);

        if ($request && $request->is('api/user*')) {
            $query->where(function($q) {
                $q->where('providerable_type', 'App\\Models\\Provider_Service')
                ->orWhere(function($sub) {
                    $sub->where('providerable_type', 'App\\Models\\Provider_Product')
                        ->where(function($inner) {
                            $inner->whereNull('quantity')
                                    ->orWhere('quantity', '>', 0);
                        });
                });
            });
        }

        $products = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        return [
            'data' => $products->map(function($product) {
                return $this->formatProductResponse($product);
            }),
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ];
    }

    */










































    public function getProductsByCategory($categoryId, $request = null, $perPage = 10)
    {
        $user = auth()->user();
        $userLat = $user->lat ?? null;
        $userLng = $user->lang ?? null;

        $query = Product::with([
                'images',
                'category',
                'discount',
                'rating',
                'providerable.user' // لاستخراج إحداثيات التاجر
            ])
            ->where('category_id', $categoryId);

        if ($request && $request->is('api/user*')) {
            $query->where(function($q) {
                $q->where('providerable_type', 'App\\Models\\Provider_Service')
                ->orWhere(function($sub) {
                    $sub->where('providerable_type', 'App\\Models\\Provider_Product')
                        ->where(function($inner) {
                            $inner->whereNull('quantity')
                                    ->orWhere('quantity', '>', 0);
                        });
                });
            });
        }

        $products = $query->orderBy('created_at', 'desc')
                        ->paginate($perPage);

        // تنسيق النتائج مع إضافة معلومات المسافة إذا كانت الإحداثيات متوفرة
        $formattedProducts = $products->map(function($product) use ($userLat, $userLng) {
            $formatted = $this->formatProductResponse($product);

            if ($userLat && $userLng && $product->providerable && $product->providerable->user) {
                $provider = $product->providerable;
                $providerUser = $provider->user;

                if ($providerUser->lat && $providerUser->lang) {
                    try {
                        $distance = $this->calculateDistance(
                            $userLat,
                            $userLng,
                            $providerUser->lat,
                            $providerUser->lang
                        );

                        $formatted['distance'] = [
                            'km' => round($distance, 2),
                            'provider_location' => [
                                'lat' => $providerUser->lat,
                                'lng' => $providerUser->lang
                            ]
                        ];
                    } catch (\Exception $e) {
                        // في حالة فشل حساب المسافة، نضيف رسالة الخطأ
                        $formatted['distance'] = [
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }

            return $formatted;
        });

        // ترتيب المنتجات حسب المسافة إذا كانت متوفرة
        if ($userLat && $userLng) {
            $formattedProducts = $formattedProducts->sortBy(function($product) {
                return $product['distance']['km'] ?? PHP_FLOAT_MAX;
            })->values();
        }

        return [
            'data' => $formattedProducts,
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'user_location' => $userLat && $userLng ? [
                'lat' => $userLat,
                'lng' => $userLng
            ] : null
        ];
    }

    /**
     * حساب المسافة بين موقعين باستخدام Haversine formula (بدون الاعتماد على Google API)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // نصف قطر الأرض بالكيلومترات

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat/2) * sin($dLat/2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon/2) * sin($dLon/2);

        $c = 2 * atan2(sqrt($a), sqrt(1-$a));

        return $earthRadius * $c;
    }


















































































































    public function getProductsByProviderProduct($id, $request = null, $perPage = 10)
    {
        $provider = Provider_Product::find($id);

        if (!$provider) {
            return null;
        }

        $query = $provider->products()
            ->with(['images', 'category', 'discount', 'rating'])
            ->orderBy('created_at', 'desc');

        if (!$request || $request->is('api/user*')) {
            $query->where(function($q) {
                $q->whereNull('quantity')
                ->orWhere('quantity', '>', 0);
            });
        }

        $products = $query->paginate($perPage);

        return [
            'data' => $products->map(function($product) {
                return $this->formatProductResponse($product);
            }),
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ];
    }

    public function getProductsByProviderService($id, $perPage = 10)
    {
        $provider = Provider_Service::find($id);

        if (!$provider) {
            return null;
        }

        $products = $provider->products()
            ->with(['images', 'category', 'discount', 'rating'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return [
            'data' => $products->map(function($product) {
                return $this->formatProductResponse($product);
            }),
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
        ];
    }

    public function getProductById($id, $request = null)
    {
        $product = Product::with([
            'images',
            'category',
            'providerable',
            'discount',
            'rating' => function($query) {
                $query->with(['user.profile', 'answer_rating'])
                    ->orderBy('created_at', 'desc');
            }
        ])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if ($request && $request->is('api/user*') &&
            $product->providerable_type === 'App\\Models\\Provider_Product' &&
            $product->quantity !== null &&
            $product->quantity <= 0) {
            return response()->json(['message' => 'Product not available'], 404);
        }

        return $this->formatProductResponse($product);
    }

    public function getProductRatings($productId, $perPage = 10)
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if ($product->providerable_type === 'App\\Models\\Provider_Product' &&
            $product->quantity !== null &&
            $product->quantity <= 0) {
            return response()->json(['message' => 'Product not available'], 404);
        }

        $ratings = Rating::with(['user.profile', 'answer_rating'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        if ($ratings->isEmpty()) {
            return response()->json(['message' => 'No rating found for this product'], 404);
        }

        return [
            'data' => $this->formatRatingResponse($ratings),
            'current_page' => $ratings->currentPage(),
            'per_page' => $ratings->perPage(),
            'total' => $ratings->total(),
        ];
    }

    public function getLatestProducts($limit = 10, $isUserRequest = false)
    {
        $query = Product::with(['images', 'category', 'discount', 'rating'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($isUserRequest) {
            $query->where(function($q) {
                $q->where('providerable_type', 'App\\Models\\Provider_Service')
                ->orWhere(function($sub) {
                    $sub->where('providerable_type', 'App\\Models\\Provider_Product')
                        ->where(function($inner) {
                            $inner->whereNull('quantity')
                                    ->orWhere('quantity', '>', 0);
                        });
                });
            });
        }

        return $query->get()->map(function($product) {
            return $this->formatProductResponse($product);
        });
    }
}
