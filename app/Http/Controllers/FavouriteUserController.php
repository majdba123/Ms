<?php

namespace App\Http\Controllers;

use App\Models\Favourite_user;
use Illuminate\Http\Request;
use App\Services\Favourite\FavouriteService;
use Illuminate\Http\JsonResponse;
use App\Models\Category;
use App\Models\Product;
class FavouriteUserController extends Controller
{
    protected $favouriteService;

    public function __construct(FavouriteService $favouriteService)
    {
        $this->favouriteService = $favouriteService;
    }
    public function addToFavourites(Request $request, $favoritable_id): JsonResponse
    {
        $user_id = auth()->id();

        // تحديد النوع استنادًا إلى التاب الوارد في الطلب
        $favoritable_type = $request->favoritable_type == 0 ? Product::class : Category::class;

        // تحقق مما إذا كان العنصر موجود بالفعل في المفضلة
        $existingFavourite = Favourite_user::where('user_id', $user_id)
            ->where('favoritable_id', $favoritable_id)
            ->where('favoritable_type', $favoritable_type)
            ->first();

        if ($existingFavourite) {
            return response()->json(['message' => 'العنصر موجود بالفعل في المفضلة'], 400);
        }

        // إضافة العنصر إلى المفضلة إذا لم يكن موجودًا
        Favourite_user::create([
            'user_id' => $user_id,
            'favoritable_id' => $favoritable_id,
            'favoritable_type' => $favoritable_type
        ]);

        return response()->json(['message' => 'تمت الإضافة إلى المفضلة'], 200);
    }


    public function removeFromFavourites(Request $request, $favoritable_id): JsonResponse
    {
        $user_id = auth()->id();
        $favoritable_type = $request->tab == 0 ? Product::class : Category::class;

        $deleted = Favourite_user::where('user_id', $user_id)
            ->where('favoritable_id', $favoritable_id)
            ->where('favoritable_type', $favoritable_type)
            ->delete();

        if ($deleted) {
            return response()->json(['message' => 'تمت إزالة العنصر من المفضلة'], 200);
        }

        return response()->json(['message' => 'العنصر غير موجود في المفضلة'], 404);
    }


   public function getFavouriteCategories(): JsonResponse
    {
        $user_id = auth()->id();

        $favourite_categories = Favourite_user::where('user_id', $user_id)
            ->where('favoritable_type', Category::class)
            ->with('favoritable')
            ->get()
            ->pluck('favoritable');

        return response()->json($favourite_categories, 200);
    }


   public function getFavouriteProducts(): JsonResponse
{
    $user_id = auth()->id();

    $favourite_products = Favourite_user::where('user_id', $user_id)
        ->where('favoritable_type', Product::class)
        ->with(['favoritable.images', 'favoritable.category'])
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->favoritable->id,
                'name' => $item->favoritable->name,
                'description' => $item->favoritable->description,
                'price' => $item->favoritable->price,
                'category' => $item->favoritable->category,
                'images' => $item->favoritable->images,
                'created_at' => $item->favoritable->created_at,
                'updated_at' => $item->favoritable->updated_at,
                'is_favourite' => true
            ];
        });

    return response()->json($favourite_products, 200);
}

}
