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
    public function addToFavourites(Request $request ,$favoritable_id): JsonResponse
    {
        $user_id = auth()->id();

        Favourite_user::create([
            'user_id' => $user_id,
            'favoritable_id' => $favoritable_id,
            'favoritable_type' => $request->favoritable_type
        ]);

        return response()->json(['message' => 'Added to favourites'], 200);
    }

    public function removeFromFavourites($favoritable_id): JsonResponse
    {
        $user_id = auth()->id();

        Favourite_user::where('user_id', $user_id)
            ->where('id', $favoritable_id)
            ->delete();

        return response()->json(['message' => 'Removed from favourites'], 200);
    }

    public function getFavouriteCategories(): JsonResponse
    {
        $user_id = auth()->id();

        $favourite_categories = Favourite_user::where('user_id', $user_id)
            ->where('favoritable_type', Category::class)
            ->with(['favoritable'])
            ->get()
            ->map(function($item) {
                return $item->favoritable;
            });

        return response()->json($favourite_categories, 200);
    }

    public function getFavouriteProducts(): JsonResponse
    {
        $user_id = auth()->id();

        $favourite_products = Favourite_user::where('user_id', $user_id)
            ->where('favoritable_type', Product::class)
            ->with(['favoritable.images', 'favoritable.category'])
            ->get()
            ->map(function($item) {
                $product = $item->favoritable;
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'category' => $product->category,
                    'images' => $product->images,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                    'is_favourite' => true // إضافة علامة للمفضلة
                ];
            });

        return response()->json($favourite_products, 200);
    }
}
