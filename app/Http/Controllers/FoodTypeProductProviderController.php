<?php

namespace App\Http\Controllers;

use App\Models\FoodType_ProductProvider;
use Illuminate\Http\Request;
use App\Models\Provider_Product;
use App\Models\User;
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
}
