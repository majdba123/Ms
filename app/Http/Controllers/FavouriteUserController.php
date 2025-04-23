<?php

namespace App\Http\Controllers;

use App\Models\Favourite_user;
use Illuminate\Http\Request;
use App\Services\Favourite\FavouriteService;

class FavouriteUserController extends Controller
{
    protected $favouriteService;

    public function __construct(FavouriteService $favouriteService)
    {
        $this->favouriteService = $favouriteService;
    }

    public function addToFavourites(Request $request, $favourite_id)
    {
        $request->validate([
            'favoritable_type' => 'required|in:0,1'
        ]);

        $favoritable_type = $request->favoritable_type == 0 ? 'App\Models\Product' : 'App\Models\Category';

        // التحقق من وجود المنتج أو الفئة
        $exists = $favoritable_type::find($favourite_id);
        if (!$exists) {
            return response()->json(['message' => $favoritable_type == 'App\Models\Product' ? 'Product not found' : 'Category not found'], 404);
        }

        // التحقق مما إذا كانت المفضلة موجودة بالفعل للمستخدم
        $user_id = auth()->user()->id; // افتراض أن المستخدم مسجل الدخول
        $favouriteExists = Favourite_user::where('user_id', $user_id)
            ->where('favoritable_id', $favourite_id)
            ->where('favoritable_type', $favoritable_type)
            ->exists();

        if ($favouriteExists) {
            return response()->json(['message' => 'Already added to favourites'], 400);
        }

        return $this->favouriteService->addToFavourites($favourite_id, $favoritable_type);
    }


    public function removeFromFavourites($favourite_id)
    {
        // التحقق من أن المستخدم هو صاحب المفضلة المراد إزالتها
        $user_id = auth()->user()->id; // افتراض أن المستخدم مسجل الدخول

        $favourite = Favourite_user::where('id', $favourite_id)
            ->where('user_id', $user_id)
            ->first();

        if (!$favourite) {
            return response()->json(['message' => 'You are not authorized to remove this favourite or it does not exist'], 403);
        }

        return $this->favouriteService->removeFromFavourites($favourite_id);
    }



    public function getFavouriteCategories()
    {
        return $this->favouriteService->getFavouriteCategories();
    }

    public function getFavouriteProducts()
    {
        return $this->favouriteService->getFavouriteProducts();
    }
}
