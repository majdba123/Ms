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
