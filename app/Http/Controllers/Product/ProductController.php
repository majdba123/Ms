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
        $providerType = $request->is('service_provider*') ? 1 : 0; // تحديد النوع بناءً على الرابط

        if ($providerType === 1) {
            $providerId = Auth::user()->Provider_service->id;

            // التحقق من أن مزود الخدمة لديه اشتراك فعال
            if (!checkActiveSubscription::checkActive($providerId)) {
                return response()->json(['message' => 'Provider does not have an active subscription'], 403);
            }

            // التحقق من أن الفئة (category) من النوع 1
            $category = Category::find($request->category_id);
            if ($category->type != 1) {
                return response()->json(['message' => 'The category must be of type 1 for service providers'], 422);
            }
        } else {
            $providerId = Auth::user()->Provider_Product->id;

            // التحقق من أن الفئة (category) من النوع 0 لمزودي المنتجات
            $category = Category::find($request->category_id);
            if ($category->type != 0) {
                return response()->json(['message' => 'The category must be of type 0 for product providers'], 422);
            }
        }
        $product = $this->productService->createProduct($request->validated(), $providerType);

        $imageUrls = [];
        foreach ($request->images as $imageFile) {
            $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = 'products_images/' . $imageName;
            $imageUrl = asset('storage/products_images/' . $imageName);

            // تخزين الصورة في التخزين
            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

            // إنشاء الصورة باستخدام الرابط الكامل
            Imag_Product::create([
                'product_id' => $product->id,
                'imag' => $imageUrl,
            ]);

            // إضافة رابط الصورة إلى الاستجابة
            $imageUrls[] = $imageUrl;
        }

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product,
            'image_urls' => $imageUrls
        ], 201);
    }




    public function update(UpdateProductRequest $request, $id): JsonResponse
    {

        $providerType = $request->is('service_provider*') ? 1 : 0; // تحديد النوع بناءً على الرابط

        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        if ($providerType === 1) {
            $providerId = Auth::user()->Provider_service->id;
            print($providerId);
            // التحقق من أن المنتج يخص المستخدم الذي تم المصادقة عليه
            if ($product->providerable_id !== $providerId || $product->providerable_type !== 'App\\Models\\Provider_Service') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // التحقق من أن مزود الخدمة لديه اشتراك فعال
            if (!checkActiveSubscription::checkActive($providerId)) {
                return response()->json(['message' => 'Provider does not have an active subscription'], 403);
            }

            // التحقق من أن الفئة (category) من النوع 1
            if ($request->has('category_id')) {
                $category = Category::find($request->category_id);
                if ($category->type != 1) {
                    return response()->json(['message' => 'The category must be of type 1 for service providers'], 422);
                }
            }
        } else {
            $providerId = Auth::user()->Provider_Product->id;

            // التحقق من أن المنتج يخص المستخدم الذي تم المصادقة عليه
            if ($product->providerable_id !== $providerId || $product->providerable_type !== 'App\\Models\\Provider_Product') {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            // التحقق من أن الفئة (category) من النوع 0 لمزودي المنتجات
            if ($request->has('category_id')) {
                $category = Category::find($request->category_id);
                if ($category->type != 0) {
                    return response()->json(['message' => 'The category must be of type 0 for product providers'], 422);
                }
            }
        }

        $updatedProduct = $this->productService->updateProduct($request->validated(), $product);

        // التحقق مما إذا كان الطلب يحتوي على صور جديدة
        if ($request->has('images')) {
            // حذف الصور القديمة
            Imag_Product::where('product_id', $product->id)->delete();
            $imageUrls = [];
            foreach ($request->images as $imageFile) {
                $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = 'products_images/' . $imageName;
                $imageUrl = asset('storage/products_images/' . $imageName);
                // تخزين الصورة في التخزين
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
                // إنشاء الصورة باستخدام الرابط الكامل
                Imag_Product::create([
                    'product_id' => $product->id,
                    'imag' => $imageUrl,
                ]);
                // إضافة رابط الصورة إلى الاستجابة
                $imageUrls[] = $imageUrl;
            }
        }

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $updatedProduct,
            'image_urls' => $imageUrls ?? []
        ], 200);
    }




    public function latest_product(Request $request): JsonResponse
    {
        // عدد المنتجات في كل صفحة
        $perPage = $request->query('per_page', 5);
        // جلب أحدث المنتجات مع pagination
        $products = Product::orderBy('created_at', 'desc')->paginate($perPage);
        // تخصيص استجابة الـ pagination مع البيانات المطلوبة
        $response = $products->toArray();
        return response()->json($response);
    }



    public function Get_By_Type(Request $request): JsonResponse
    {
        // الحصول على نوع المزود من معاملات الاستعلام (query parameters)
        $providerType = $request->query('type');
        // التحقق من أن type قد تم تمريره كمعامل استعلام وأن قيمته إما 0 أو 1
        if ($providerType === null || !in_array($providerType, [0, 1])) {
            return response()->json(['message' => 'Invalid provider type. Type must be 0 or 1.'], 422);
        }
        // جلب المنتجات بناءً على نوع المزود باستخدام الخدمة
        $products = $this->productService->getProductsByType($providerType);

        return response()->json($products);
    }



    public function Get_By_Category($id): JsonResponse
    {
        // التحقق من وجود الفئة
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Category not found.'], 404);
        }

        // جلب المنتجات بناءً على الفئة باستخدام الخدمة
        $products = $this->productService->getProductsByCategory($id);
        return response()->json($products);
    }



    public function Get_By_Product($id)
    {
        $products = $this->productService->getProductsByProviderProduct($id);

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


    public function getProductById($id)
    {
        $product = $this->productService->getProductById($id);

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


        $providerType = $request->is('service_provider*') ? 1 : 0; // تحديد النوع بناءً على الرابط

        if ($providerType === 1) {
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
