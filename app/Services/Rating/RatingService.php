<?php

namespace App\Services\Rating;

use App\Models\Product;
use App\Models\Rating;
use Illuminate\Support\Facades\Auth;

class RatingService
{
    public function rateProduct($productId, $data)
    {
        $product = Product::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        $userId = Auth::id();
        $rating = Rating::create([
            'user_id' => $userId,
            'product_id' => $productId,
            'num' => $data['num'],
            'comment' => $data['comment'],
        ]);
        return $rating;
    }

    public function updateRating($ratingId, $data)
    {
        // البحث عن التقييم بواسطة المعرف
        $rating = Rating::findOrFail($ratingId);
        // التحقق من أن المستخدم هو صاحب التقييم
        if ($rating->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // تحديث التقييم
        $rating->update($data);
        return $rating;
    }


    public function deleteRating($ratingId)
    {
        // البحث عن التقييم بواسطة المعرف
        $rating = Rating::findOrFail($ratingId);

        // التحقق من أن المستخدم هو صاحب التقييم
        if ($rating->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // حذف التقييم
        $rating->delete();

        return response()->json(['message' => 'Rating deleted successfully'], 200);
    }


    public function getUserRatings()
    {
        $userId = Auth::id();
        // استرجاع جميع التقييمات للمستخدم المصادق عليه
        $ratings = Rating::where('user_id', $userId)->get();

        return $ratings;
    }
}
