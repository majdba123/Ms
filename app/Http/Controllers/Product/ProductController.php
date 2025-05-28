<?php


namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\FilterProduct;
use Illuminate\Support\Facades\Log;

use App\Services\Product\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Helpers\checkActiveSubscription;
use App\Models\Category;
use App\Models\Category_Vendor;
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


    public function store(StoreProductRequest $request): JsonResponse
    {
        // Start database transaction
        DB::beginTransaction();

        try {
            $providerType = $request->is('api/service_provider*') ? 1 : 0;

            if ($providerType === 1) {
                $providerId = Auth::user()->Provider_service->id;

                // Check active subscription
                if (!checkActiveSubscription::checkActive($providerId)) {
                    return response()->json(['message' => 'Provider does not have an active subscription'], 403);
                }

                // Verify category type
                $category = Category::find($request->category_id);
                if ($category->type != 1) {
                    return response()->json(['message' => 'The category must be of type 1 for service providers'], 422);
                }
            } else {
                $providerId = Auth::user()->Provider_Product->id;

                // Verify category type
                $category = Category::find($request->category_id);
                if ($category->type != 0) {
                    return response()->json(['message' => 'The category must be of type 0 for product providers'], 422);
                }
            }

            // Create product with transaction
            $product = $this->productService->createProduct($request->validated(), $providerType);

            // Process images
            $uploadedImages = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                    $imagePath = 'products/' . $imageName;
                    Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                    // Create image record
                    $image = Imag_Product::create([
                        'product_id' => $product->id,
                        'imag' => $imagePath ? asset('storage/' . $imagePath) : null,
                    ]);

                    $uploadedImages[] = $image->imag;
                }
            }

            // Commit transaction if everything is successful
            DB::commit();

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product,
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
        // Start database transaction
        DB::beginTransaction();

        try {
            $providerType = $request->is('api/service_provider*') ? 1 : 0;
            $product = Product::find($id);

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            if ($providerType === 1) {
                $providerId = Auth::user()->Provider_service->id;

                // Verify ownership
                if ($product->providerable_id != $providerId || $product->providerable_type != 'App\\Models\\Provider_Service') {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }

                // Check active subscription
                if (!checkActiveSubscription::checkActive($providerId)) {
                    return response()->json(['message' => 'Provider does not have an active subscription'], 403);
                }

                // Verify category type if being updated
                if ($request->has('category_id')) {
                    $category = Category::find($request->category_id);
                    if ($category->type != 1) {
                        return response()->json(['message' => 'The category must be of type 1 for service providers'], 422);
                    }
                }
            } else {
                $providerId = Auth::user()->Provider_Product->id;

                // Verify ownership
                if ($product->providerable_id != $providerId || $product->providerable_type != 'App\\Models\\Provider_Product') {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }

                // Verify category type if being updated
                if ($request->has('category_id')) {
                    $category = Category::find($request->category_id);
                    if ($category->type != 0) {
                        return response()->json(['message' => 'The category must be of type 0 for product providers'], 422);
                    }
                }
            }

            // Update product
            $updatedProduct = $this->productService->updateProduct($request->validated(), $product);

            // Handle image updates if present
            $imageUrls = [];
            if ($request->has('images')) {
                // Delete old images
                $oldImages = Imag_Product::where('product_id', $product->id)->get();

                // Store new images
                foreach ($request->images as $imageFile) {
                    $imageName = Str::random(32).'.'.$imageFile->getClientOriginalExtension();
                    $imagePath = 'products_images/'.$imageName;

                    // Store image
                    Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                    // Create image record
                    $image = Imag_Product::create([
                        'product_id' => $product->id,
                        'imag' => asset('/storage/products_images/'.$imageName),
                    ]);

                    $imageUrls[] = $image->imag;
                }

                // Delete old image files from storage after new ones are successfully uploaded
                foreach ($oldImages as $oldImage) {
                    $oldImagePath = str_replace(asset('storage/'), '', $oldImage->imag);
                    Storage::disk('public')->delete($oldImagePath);
                    $oldImage->delete();
                }
            }

            // Commit transaction if everything is successful
            DB::commit();

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $updatedProduct,
                'image_urls' => $imageUrls
            ], 200);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollBack();

            // Log the error

            return response()->json([
                'message' => 'Product update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function latest_product(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 5);

        $query = Product::orderBy('created_at', 'desc')->with('images');

        // Filter out products with quantity < 1 for user requests
        if ($request->is('api/user*')) {
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

        $products = $query->paginate($perPage);

        return response()->json($products->toArray());
    }

    public function Get_By_Type(Request $request): JsonResponse
    {
        $providerType = $request->query('type');

        if ($providerType === null || !in_array($providerType, [0, 1])) {
            return response()->json(['message' => 'Invalid provider type. Type must be 0 or 1.'], 422);
        }

        // Pass the request to service to check if it's from user
        $products = $this->productService->getProductsByType($providerType, $request);

        return response()->json($products);
    }

   public function Get_By_Category($id, Request $request): JsonResponse
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        // Pass the request to service to check if it's from user
        $products = $this->productService->getProductsByCategory($id, $request);
        return response()->json($products);
    }


    public function Get_By_Product(Request $request, $id)
    {
        $products = $this->productService->getProductsByProviderProduct($id, $request);

        if (is_null($products)) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        return response()->json($products);
    }

    public function Get_By_Service($id)
    {
        $products = $this->productService->getProductsByProviderService($id);

        if (is_null($products)) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        return response()->json($products);
    }


    public function getProductById($id, Request $request)
    {
        // Pass the request to service to check if it's from user
        $product = $this->productService->getProductById($id, $request);

        if ($product instanceof \Illuminate\Http\JsonResponse) {
            return $product;
        }

        return response()->json($product, 200);
    }

    public function getProductRatings($id)
    {
        $ratings = $this->productService->getProductRatings($id);

        if ($ratings instanceof \Illuminate\Http\JsonResponse) {
            return $ratings;
        }

        return response()->json($ratings, 200);
    }



    public function destroy($id): JsonResponse
    {


        $result = $this->productService->deleteProduct($id);
        return response()->json(['message' => $result['message']], $result['status']);
    }



    public function getProviderProducts(FilterProduct $request)
    {


        $providerType = $request->is('api/service_provider*') ? 1 : 0; // تحديد النوع بناءً على الرابط

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

        return $query->get();
    }

    public function show($product_id)
    {
        $product = Product::find($product_id);

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

        $result = $this->productService->getProductById($product_id);


        return $result ;

    }


}
