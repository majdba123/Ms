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

        // إرجاع التقييم مع العلاقات
        return Rating::with(['user.Profile', 'answer_rating'])->find($rating->id);
    }

    public function updateRating($ratingId, $data)
    {
        $rating = Rating::findOrFail($ratingId);

        if ($rating->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rating->update($data);

        // إرجاع التقييم المحدث مع العلاقات
        return Rating::with(['user.Profile', 'answer_rating'])->find($rating->id);
    }

    public function deleteRating($ratingId)
    {
        $rating = Rating::findOrFail($ratingId);

        if ($rating->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rating->delete();

        return response()->json(['message' => 'Rating deleted successfully'], 200);
    }

    public function getUserRatings()
    {
        $userId = Auth::id();

        // استرجاع التقييمات مع العلاقات
        return Rating::with(['user.Profile', 'answer_rating', 'product'])
                    ->where('user_id', $userId)
                    ->get()
                    ->map(function($rating) {
                        return $rating->toArray();
                    });
    }

    public function GetAllRateProduct($product_id)
    {
        // استرجاع جميع التقييمات للمنتج مع العلاقات
        return Rating::with(['user.Profile', 'answer_rating'])
                    ->where('product_id', $product_id)
                    ->get()
                    ->map(function($rating) {
                        return $rating->toArray();
                    });
    }
}
