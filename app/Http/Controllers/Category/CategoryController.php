<?php
namespace App\Http\Controllers\Category;
use App\Http\Controllers\Controller;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Services\Category\CategoryService;
use App\Models\Category;
use App\Models\Product;
use App\Models\Provider_Product;
use App\Models\Provider_Service;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }
    public function index(Request $request): JsonResponse
    {
        // إنشاء الفاليديتور للتحقق من صحة معامل الاستعلام type
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|integer|in:0,1,2', // تحقق من أن type هو رقم صحيح ويجب أن يكون إما 0 أو 1
        ]);

        // التحقق من صحة البيانات
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // استقبال النوع من معامل الاستعلام بعد التحقق
        $type = $request->query('type');

        // جلب الكاتيغوري بناءً على النوع
        $categories = $this->categoryService->getAll($type);

        return response()->json($categories);
    }


    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());
        return response()->json(['message' => 'Category created successfully', 'category' => $category], 201);
    }

    public function show($id): JsonResponse
    {
        $category = $this->categoryService->getById($id);
        return response()->json($category);
    }

    public function update(UpdateCategoryRequest $request, $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category = $this->categoryService->update($category, $request->validated());
        return response()->json(['message' => 'Category updated successfully', 'category' => $category]);
    }

    public function destroy($id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $this->categoryService->delete($category);
        return response()->json(['message' => 'Category deleted successfully']);
    }


    public function category_provider(Request $request): JsonResponse
    {
        $type = 0; // افتراضيًا لنوع 0

        if ($request->is('api/service_provider*')) {
            $type = $request->query('type', 1); // يستخدم النوع المرسل في الطلب
        } elseif ($request->is('api/product_provider*')) {
            // إذا كان المستخدم من نوع food_provider نستخدم النوع 2، وإلا النوع 0
            $type = auth()->user()->type == 'food_provider' ? 2 : 0;
        }

        $categories = $this->categoryService->getByType($type);

        return response()->json($categories);
    }



public function getProvidersByCategory($categoryId)
{
    $category = Category::findOrFail($categoryId);

    // تحديد نوع المزودين المطلوبين بناءً على نوع الفئة
    $providerModel = $category->type == 0 || $category->type == 2
        ? Provider_Product::class
        : Provider_Service::class;

    // الحصول على المزودين الذين لديهم منتجات/خدمات في هذه الفئة
    $providers = $providerModel::whereHas('products', function($query) use ($categoryId) {
            $query->where('category_id', $categoryId);
        })
        ->with(['user', 'user.provider_product'])
        ->get()
        ->filter(function($provider) use ($category) {
            // إذا كانت الفئة من النوع 2، نرجع فقط مزودي الطعام
            if ($category->type == 2) {
                return $provider->user->type == 'food_provider';
            }
            return true;
        })
        ->map(function($provider) {
            return [
                'id' => $provider->id,
                'status' => $provider->status,
                'user' => $provider->user,
                'type' => $provider->user->type, // إضافة نوع المزود
                'image' => $provider->user->Profile->image ?? null,
                'address' => $provider->user->Profile->address ?? null,
                'lat' => $provider->user->Profile->lat ?? null,
                'lang' => $provider->user->Profile->lang ?? null,
                // يمكن إضافة المزيد من الحقول حسب الحاجة
            ];
        });

    return response()->json([
        'success' => true,
        'category' => $category->only(['id', 'name', 'type']),
        'providers' => $providers
    ]);
}

}
