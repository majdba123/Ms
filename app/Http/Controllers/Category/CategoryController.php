<?php
namespace App\Http\Controllers\Category;
use App\Http\Controllers\Controller;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Services\Category\CategoryService;
use App\Models\Category;
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
            'type' => 'sometimes|integer|in:0,1', // تحقق من أن type هو رقم صحيح ويجب أن يكون إما 0 أو 1
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
            $type = 0; // تعيين النوع 0 إذا كان الطلب يبدأ بـ product_provider
        }

        $categories = $this->categoryService->getByType($type);

        return response()->json($categories);
    }

}
