<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\FilterProduct;
use Illuminate\Support\Facades\Log;
use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Helpers\checkActiveSubscription;
use App\Models\Category;
use App\Models\Imag_Product;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

   /* public function store(StoreProductRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $providerType = $request->is('api/service_provider*') ? 1 : 0;

            if ($providerType === 1) {
                $providerId = Auth::user()->Provider_service->id;

                if (!checkActiveSubscription::checkActive($providerId)) {
                    return response()->json(['message' => 'Provider does not have an active subscription'], 403);
                }

                $category = Category::find($request->category_id);
                if ($category->type != 1) {
                    return response()->json(['message' => 'The category must be of type 1 for service providers'], 422);
                }
            } else {
                $providerId = Auth::user()->Provider_Product->id;

                $category = Category::find($request->category_id);
                if ($category->type != 0) {
                    return response()->json(['message' => 'The category must be of type 0 for product providers'], 422);
                }
            }

            $product = $this->productService->createProduct($request->validated(), $providerType);

            $uploadedImages = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                    $imagePath = 'products/' . $imageName;
                    Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                    $image = Imag_Product::create([
                        'product_id' => $product->id,
                        'imag' => $imagePath ? asset('storage/' . $imagePath) : null,
                    ]);

                    $uploadedImages[] = $image->imag;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $this->productService->formatProductResponse($product),
                'image_urls' => $uploadedImages
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Product creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }*/

public function store(StoreProductRequest $request): JsonResponse
{
    DB::beginTransaction();

    try {
        // تحديد نوع البروفايدر ورقمه
        if ($request->is('api/admin*')) {
            // إذا كان المستخدم أدمن، نستخدم البيانات المرسلة في الـ request
            $providerType = $request->provider_type;
            $providerId = $request->provider_id;
        } else {
            // إذا كان المستخدم تاجراً، نحدد النوع بناءً على مسار الطلب
            if ($request->is('api/service_provider*')) {
                $providerType = 1;
                $providerId = Auth::user()->Provider_service->id;
            } else {
                $providerType = 0;
                $providerId = Auth::user()->Provider_Product->id;
            }
        }

        // التحقق من الاشتراك النشط (إذا كان الطلب ليس من الأدمن)
        if (!$request->is('api/admin*') && $providerType == 1) {
            if (!checkActiveSubscription::checkActive($providerId)) {
                return response()->json(['message' => 'Provider does not have an active subscription'], 403);
            }
        }

        // التحقق من نوع الفئة
        $category = Category::find($request->category_id);
        if ($category->type != $providerType) {
            $typeName = $providerType == 1 ? 'service providers' : 'product providers';
            return response()->json(['message' => "The category must be of type $providerType for $typeName"], 422);
        }

        // إنشاء المنتج
        $productData = $request->validated();
        $productData['provider_type'] = $providerType;
        $productData['provider_id'] = $providerId;

        $product = $this->productService->createProduct($productData, $providerType);

        // رفع الصور بنفس الطريقة السابقة
        $uploadedImages = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $imageFile) {
                $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = 'products/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                $imageUrl = url('api/storage/' . $imagePath); // استخدام url بدلاً من asset

                $image = Imag_Product::create([
                    'product_id' => $product->id,
                    'imag' => $imageUrl,
                ]);

                $uploadedImages[] = $imageUrl;
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $this->productService->formatProductResponse($product),
            'image_urls' => $uploadedImages
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Product creation failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Product creation failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

   public function update(UpdateProductRequest $request, $id): JsonResponse
{
    DB::beginTransaction();

    try {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // إذا كان المستخدم أدمن، نتخطى التحقق من الملكية
        if (!$request->is('api/admin*')) {
            $providerType = $request->is('api/service_provider*') ? 1 : 0;

            if ($providerType === 1) {
                $providerId = Auth::user()->Provider_service->id;

                if ($product->providerable_id != $providerId || $product->providerable_type != 'App\\Models\\Provider_Service') {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }

                if (!checkActiveSubscription::checkActive($providerId)) {
                    return response()->json(['message' => 'Provider does not have an active subscription'], 403);
                }
            } else {
                $providerId = Auth::user()->Provider_Product->id;

                if ($product->providerable_id != $providerId || $product->providerable_type != 'App\\Models\\Provider_Product') {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
            }
        }

        // التحقق من نوع الفئة إذا تم إرسال category_id
        if ($request->has('category_id')) {
            $category = Category::find($request->category_id);
            $expectedType = $request->is('api/admin*')
                ? ($product->providerable_type == 'App\\Models\\Provider_Service' ? 1 : 0)
                : ($request->is('api/service_provider*') ? 1 : 0);

            if ($category->type != $expectedType) {
                $typeName = $expectedType == 1 ? 'service providers' : 'product providers';
                return response()->json(['message' => "The category must be of type $expectedType for $typeName"], 422);
            }
        }

        $updatedProduct = $this->productService->updateProduct($request->validated(), $product);

        $imageUrls = [];
        if ($request->has('images')) {
            $oldImages = Imag_Product::where('product_id', $product->id)->get();

            foreach ($request->images as $imageFile) {
                $imageName = Str::random(32).'.'.$imageFile->getClientOriginalExtension();
                $imagePath = 'products/'.$imageName;

                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                $imageUrl = url('api/storage/' . $imagePath); // استخدام url بدلاً من asset

                $image = Imag_Product::create([
                    'product_id' => $product->id,
                    'imag' => $imageUrl,
                ]);

                $imageUrls[] = $imageUrl;
            }

            // حذف الصور القديمة بنفس الطريقة السابقة
            foreach ($oldImages as $oldImage) {
                $this->deleteOldImage($oldImage->imag);
                $oldImage->delete();
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $this->productService->formatProductResponse($updatedProduct),
            'image_urls' => $imageUrls
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Product update failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Product update failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

protected function deleteOldImage(string $imageUrl)
{
    try {
        $basePath = url('api/storage');
        $relativePath = str_replace($basePath, '', $imageUrl);
        $relativePath = ltrim($relativePath, '/');

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    } catch (\Exception $e) {
        Log::error("Failed to delete old product image: " . $e->getMessage());
    }
}










    public function latest_product(Request $request): JsonResponse
    {
        $products = $this->productService->getLatestProducts($request->query('per_page', 5), $request->is('api/user*'));
        return response()->json($products);
    }

    public function Get_By_Type(Request $request): JsonResponse
    {
        $providerType = $request->query('type');

        if ($providerType === null || !in_array($providerType, [0, 1])) {
            return response()->json(['message' => 'Invalid provider type. Type must be 0 or 1.'], 422);
        }

        $products = $this->productService->getProductsByType($providerType, $request);
        return response()->json($products);
    }

    public function Get_By_Category($id, Request $request): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        $products = $this->productService->getProductsByCategory($id, $request);
        return response()->json($products);
    }

    public function Get_By_Product(Request $request, $id): JsonResponse
    {
        $products = $this->productService->getProductsByProviderProduct($id, $request);

        if (is_null($products)) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        return response()->json($products);
    }

    public function Get_By_Service($id): JsonResponse
    {
        $products = $this->productService->getProductsByProviderService($id);

        if (is_null($products)) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        return response()->json($products);
    }

    public function getProductById($id, Request $request): JsonResponse
    {
        $product = $this->productService->getProductById($id, $request);

        if ($product instanceof JsonResponse) {
            return $product;
        }

        return response()->json($product);
    }

    public function getProductRatings($id): JsonResponse
    {
        $ratings = $this->productService->getProductRatings($id);

        if ($ratings instanceof JsonResponse) {
            return $ratings;
        }

        return response()->json($ratings);
    }

    public function destroy($id): JsonResponse
    {
        $result = $this->productService->deleteProduct($id);
        return response()->json(['message' => $result['message']], $result['status']);
    }

    public function getProviderProducts(FilterProduct $request): JsonResponse
    {
        $providerType = $request->is('api/service_provider*') ? 1 : 0;

        if ($providerType == 1) {
            $providerId = Auth::user()->Provider_service->id;
            $query = Product::where('providerable_id', $providerId)
                ->where('providerable_type','App\\Models\\Provider_Service');
        } else {
            $providerId = Auth::user()->Provider_Product->id;
            $query = Product::where('providerable_id', $providerId)
                ->where('providerable_type', 'App\\Models\\Provider_Product');
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        if ($request->has('price')) {
            $query->where('price', $request->price);
        }

        $products = $query->with(['images', 'category', 'rating', 'discount'])->get();

        $formattedProducts = $products->map(function($product) {
            return $this->productService->formatProductResponse($product);
        });

        return response()->json($formattedProducts);
    }

    public function show($product_id): JsonResponse
    {
        $product = Product::find($product_id);

        if (!$product) {
            return response()->json(['message' => 'Product not found', 'status' => 404]);
        }

        $user = Auth::user();
        $providerableId = $product->providerable_id;
        $providerableType = $product->providerable_type;

        $provider = $providerableType::find($providerableId);

        if (!$provider || $provider->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized', 'status' => 403]);
        }

        $result = $this->productService->getProductById($product_id);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json($result);
    }
}
