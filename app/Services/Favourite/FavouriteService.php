<?php

namespace App\Services\Favourite;

use App\Models\Favourite_user;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class FavouriteService
{
    public function addToFavourites($favoritable_id, $favoritable_type): JsonResponse
    {
        $user_id = auth()->user()->id; // افتراض أن المستخدم مسجل الدخول

        Favourite_user::create([
            'user_id' => $user_id,
            'favoritable_id' => $favoritable_id,
            'favoritable_type' => $favoritable_type
        ]);

        return response()->json(['message' => 'Added to favourites'], 200);
    }



    public function removeFromFavourites($favoritable_id): JsonResponse
    {
        $user_id = auth()->user()->id; // افتراض أن المستخدم مسجل الدخول

        Favourite_user::where('user_id', $user_id)
            ->where('id', $favoritable_id)
            ->delete();

        return response()->json(['message' => 'Removed from favourites'], 200);
    }

    public function getFavouriteCategories(): JsonResponse
    {
        $user_id = auth()->user()->id; // افتراض أن المستخدم مسجل الدخول

        $favourite_categories = Favourite_user::where('user_id', $user_id)
            ->where('favoritable_type', Category::class)
            ->with('favoritable')
            ->get()
            ->pluck('favoritable');

        return response()->json($favourite_categories, 200);
    }

    public function getFavouriteProducts(): JsonResponse
    {
        $user_id = auth()->user()->id; // افتراض أن المستخدم مسجل الدخول

        $favourite_products = Favourite_user::where('user_id', $user_id)
            ->where('favoritable_type', Product::class)
            ->with('favoritable')
            ->get()
            ->pluck('favoritable');

        return response()->json($favourite_products, 200);
    }
}
