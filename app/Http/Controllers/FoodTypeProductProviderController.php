<?php

namespace App\Http\Controllers;

use App\Models\FoodType;
use App\Models\FoodType_ProductProvider;
use Illuminate\Http\Request;
use App\Models\Provider_Product;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FoodTypeProductProviderController extends Controller
{
    public function getFoodTypesByProvider($id)
    {
        // جلب معلومات منتج المزود
        $providerProduct = Provider_Product::find($id);

        if (!$providerProduct) {
            return response()->json(['error' => 'Product provider not found'], 404);
        }

        // جلب معلومات المستخدم المالك لهذا المنتج
        $user = User::find($providerProduct->user_id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // التحقق من نوع المستخدم
        if ($user->type !== 'food_provider') {
            return response()->json(['error' => 'User is not a food provider'], 400);
        }

        // جلب أنواع الطعام المرتبطة بهذا المنتج
        $foodTypes = FoodType_ProductProvider::where('provider__product_id', $id)
            ->with('food_type')
            ->get()
            ->pluck('food_type');

        return response()->json(['food_types' => $foodTypes]);
    }



    public function getProvidersByFoodType($id)
    {
        // البحث عن نوع الطعام
        $foodType = FoodType::find($id);

        if (!$foodType) {
            return response()->json(['error' => 'Food type not found'], 404);
        }

        // جلب جميع العلاقات بين نوع الطعام ومنتجات المزودين
        $foodTypeProviders = FoodType_ProductProvider::where('food_type_id', $id)
            ->with(['product_provider.user.profile', 'product_provider.products'])
            ->get();

        // تحضير البيانات للإرجاع
        $providers = $foodTypeProviders->map(function ($item) {
            $providerProduct = $item->product_provider;
            $user = $providerProduct->user;

            return [
                'provider_food' => [
                    'id' => $providerProduct->id ?? null,
                    'status' => $providerProduct->status ?? null,
                    'image' => $user->profile->image ?? null,
                    'address' => $user->profile->address ?? null,
                    'lang' => $user->profile->lang ?? null,
                    'lat' => $user->profile->lat ?? null,
                ],
                'user' => [
                    'id' => $user->id ?? null,
                    'name' => $user->name ?? null,
                    'email' => $user->email ?? null,
                    'national_id' => $user->national_id ?? null,
                    'image_national_id' => $user->image_path ?? null,
                ],
                'products' => $providerProduct->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                        // يمكنك إضافة المزيد من حقول المنتج حسب الحاجة
                    ];
                })->toArray()
            ];
        });

        return response()->json(['providers' => $providers]);
    }



     public function add($food_type_id)
    {
                    $foodType = FoodType::findOrFail($food_type_id);

        // الحصول على مزود المنتجات للمستخدم الحالي
        $providerProduct = Provider_Product::where('user_id', Auth::id())->first();

        if (!$providerProduct) {
            return response()->json(['error' => 'Provider product not found for this user'], 404);
        }

        // التحقق من عدم وجود العلاقة بالفعل
        $existingRelation = FoodType_ProductProvider::where([
            'provider__product_id' => $providerProduct->id,
            'food_type_id' => $food_type_id
        ])->exists();

        if ($existingRelation) {
            return response()->json(['error' => 'This food type is already assigned to your provider'], 400);
        }

        // إنشاء العلاقة
        $relation = FoodType_ProductProvider::create([
            'provider__product_id' => $providerProduct->id,
            'food_type_id' => $food_type_id
        ]);

        return response()->json([
            'message' => 'Food type added successfully',
            'relation' => $relation
        ], 201);
    }

    // إزالة نوع طعام من مزود المنتجات الحالي
    public function remove($food_type_id)
    {

                    $foodType = FoodType::findOrFail($food_type_id);

        // الحصول على مزود المنتجات للمستخدم الحالي
        $providerProduct = Provider_Product::where('user_id', Auth::id())->first();

        if (!$providerProduct) {
            return response()->json(['error' => 'Provider product not found for this user'], 404);
        }

        // البحث عن العلاقة وحذفها
        $deleted = FoodType_ProductProvider::where([
            'provider__product_id' => $providerProduct->id,
            'food_type_id' => $food_type_id
        ])->delete();

        if ($deleted) {
            return response()->json(['message' => 'Food type removed successfully']);
        }

        return response()->json(['error' => 'Relation not found'], 404);
    }

    // الحصول على أنواع الطعام للمزود الحالي
    public function getMyFoodTypes()
    {
        // الحصول على مزود المنتجات للمستخدم الحالي
        $providerProduct = Provider_Product::where('user_id', Auth::id())->first();

        if (!$providerProduct) {
            return response()->json(['error' => 'Provider product not found for this user'], 404);
        }

        // جلب أنواع الطعام المرتبطة بهذا المزود
        $foodTypes = FoodType_ProductProvider::where('provider__product_id', $providerProduct->id)
            ->with('food_type')
            ->get()
            ->pluck('food_type');

        return response()->json(['food_types' => $foodTypes]);
    }

}
