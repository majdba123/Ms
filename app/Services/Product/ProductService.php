<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\Provider_Product;
use App\Models\Provider_Service;
use App\Models\Rating;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProductService
{
   public function createProduct(array $data, $providerType): Product
    {
        $providerTypeClass = $providerType === 1
            ? 'App\\Models\\Provider_Service'
            : 'App\\Models\\Provider_Product';

        $providerId = $providerType === 1
            ? Auth::user()->Provider_service->id
            : Auth::user()->Provider_Product->id;

        return Product::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => $data['price'],
            'qunatity' => $data['qunatity'],
            'time_of_service' => $data['time_of_service'] ?? null,
            'category_id' => $data['category_id'],
            'providerable_id' => $providerId,
            'providerable_type' => $providerTypeClass,
        ]);
    }
    public function updateProduct(array $data, Product $product): Product
    {
        $updateData = [
            'name' => $data['name'] ?? $product->name,
            'description' => $data['description'] ?? $product->description,
            'price' => $data['price'] ?? $product->price,
            'qunatity' => $data['qunatity'] ?? $product->qunatity,
            'category_id' => $data['category_id'] ?? $product->category_id,
        ];

        // Only update time_of_service if it's provided or if it's a service provider
        if (array_key_exists('time_of_service', $data)) {
            $updateData['time_of_service'] = $data['time_of_service'];
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

        if (!$provider || $provider->user_id !== $user->id) {
            return ['message' => 'Unauthorized', 'status' => 403];
        }

        $product->delete();

        return ['message' => 'Product deleted successfully', 'status' => 200];
    }

    public function getProductsByType($providerType, $perPage = 10)
    {
        $query = Product::with(['images', 'category'])
            ->orderBy('created_at', 'desc');

        if ($providerType == 0) {
            $query->where('providerable_type', 'App\\Models\\Provider_Product');
        } else {
            $query->where('providerable_type', 'App\\Models\\Provider_Service');
        }

        return $query->paginate($perPage);
    }

    public function getProductsByCategory($categoryId, $perPage = 10)
    {
        return Product::with(['images', 'category'])
            ->where('category_id', $categoryId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getProductsByProviderProduct($id, $perPage = 10)
    {
        $provider = Provider_Product::with(['products' => function($query) {
            $query->with(['images', 'category'])
                ->orderBy('created_at', 'desc');
        }])->find($id);

        if (!$provider) {
            return null;
        }

        return $provider->products()->paginate($perPage);
    }

    public function getProductsByProviderService($id, $perPage = 10)
    {
        $provider = Provider_Service::with(['products' => function($query) {
            $query->with(['images', 'category'])
                ->orderBy('created_at', 'desc');
        }])->find($id);

        if (!$provider) {
            return null;
        }

        return $provider->products()->paginate($perPage);
    }

    public function getProductById($id)
    {
        $product = Product::with([
            'images',
            'category',
            'providerable',
            'rating' => function($query) {
                $query->with(['user.profile', 'answer_rating'])
                    ->orderBy('created_at', 'desc');
            }
        ])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $formattedProduct = $product->toArray();
        $formattedProduct['rating'] = $product->rating->map(function($rating) {
            return [
                'id' => $rating->id,
                'num' => $rating->num,
                'comment' => $rating->comment,
                'created_at' => $rating->created_at,
                'user' => [
                    'id' => $rating->user->id,
                    'name' => $rating->user->name,
                    'image' => $rating->user->profile->image ?? null
                ],
                'answers' => $rating->answer_rating
            ];
        });

        return $formattedProduct;
    }

    public function getProductRatings($productId, $perPage = 10)
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $rating = Rating::with(['user.profile', 'answer_rating'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(function($rating) {
                return [
                    'id' => $rating->id,
                    'num' => $rating->num,
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at,
                    'user' => [
                        'id' => $rating->user->id,
                        'name' => $rating->user->name,
                        'image' => $rating->user->profile->image ?? null
                    ],
                    'answers' => $rating->answer_rating
                ];
            });

        if ($rating->isEmpty()) {
            return response()->json(['message' => 'No rating found for this product'], 404);
        }

        return $rating;
    }

    public function getLatestProducts($limit = 10)
    {
        return Product::with(['images', 'category'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
