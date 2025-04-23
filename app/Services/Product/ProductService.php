<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\Provider_Product;
use App\Models\Provider_Service;
use App\Models\Rating;
use Illuminate\Support\Facades\Auth;

class ProductService
{
    public function createProduct(array $data, $providerType)
    {
        // تحديد نوع المزود (مزود خدمة أو مزود منتج) بناءً على البيانات المستلمة
        $providerTypeClass = $providerType === 1 ? 'App\\Models\\Provider_Service' : 'App\\Models\\Provider_Product';

        // جلب ID المزود من المستخدم الذي تم المصادقة عليه
        $providerId = $providerType === 1 ? Auth::user()->Provider_service->id : Auth::user()->Provider_Product->id;

        // إنشاء المنتج وربطه بالعلاقة البولي مورفيك
        return Product::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'price' => $data['price'],
            'category_id' => $data['category_id'],
            'providerable_id' => $providerId,
            'providerable_type' => $providerTypeClass,
        ]);
    }


    public function updateProduct(array $data, $product)
    {
        $providerTypeClass = $product->providerable_type;

        // تحديث المنتج الموجود
        $product->update([
            'name' => $data['name'] ?? $product->name,
            'description' => $data['description'] ?? $product->description,
            'price' => $data['price'] ?? $product->price,
            'category_id' => $data['category_id'] ?? $product->category_id,
        ]);

        return $product;
    }

    public function deleteProduct($id): array
    {
        $product = Product::find($id);

        if (!$product) {
            return ['message' => 'Product not found', 'status' => 404];
        }

        $user = Auth::user();
        $providerableId = $product->providerable_id;
        $providerableType = $product->providerable_type;

        // تحقق من أن الـ providerable_type يتطابق مع نوع الـ provider المستخدم و قم بتحميل المزود المرتبط بالمنتج
        $provider = $providerableType::find($providerableId);

        // التحقق من أن المنتج يخص المستخدم الذي تم المصادقة عليه
        if (!$provider || $provider->user_id !== $user->id) {
            return ['message' => 'Unauthorized', 'status' => 403];
        }

        // تنفيذ عملية الحذف باستخدام الـ "Soft Delete"
        $product->delete();

        return ['message' => 'Product deleted successfully', 'status' => 200];
    }



    public function getProductsByType($providerType)
    {
        if ($providerType == 0) {
            return Product::with('images')->where('providerable_type', 'App\\Models\\Provider_Product')->get();
        } else {
            return Product::with('images')->where('providerable_type', 'App\\Models\\Provider_Service')->get();
        }
    }



    public function getProductsByCategory($categoryId)
    {
        return Product::with('images')->where('category_id', $categoryId)->get();
    }


    public function getProductsByProviderProduct($id)
    {
        $provider = Provider_Product::with('products.images')->find($id);

        if (!$provider) {
            return null;
        }

        return $provider->products;
    }


    public function getProductsByProviderService($id)
    {
        $provider = Provider_Service::with('products.images')->find($id);

        if (!$provider) {
            return null;
        }

        return $provider->products;
    }


    public function getProductById($id)
    {
        $product = Product::with('images')->find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return $product;
    }


    public function getProductRatings($productId)
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        // جلب التقييمات بناءً على معرّف المنتج
        $ratings = Rating::where('product_id', $productId)->get();

        if ($ratings->isEmpty()) {
            return response()->json(['message' => 'No ratings found for this product'], 404);
        }

        return $ratings;
    }
}
