<?php

namespace App\Services\AnswerRating;

use App\Models\Rating;
use App\Models\Product;
use App\Models\Answer_Rating;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AnswerRatingService
{
    public function storeAnswer(Request $request, $rate_id)
    {
        // جلب التقييم بناءً على rate_id
        $rating = Rating::find($rate_id);

        if (!$rating) {
            return response()->json(['message' => 'Rating not found'], 404);
        }

        // جلب المنتج المفيم من التقييم
        $product = Product::find($rating->product_id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // التحقق من أن المستخدم هو صاحب المنتج
        if ($product->providerable->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // تخزين الرد
        $answerRating = Answer_Rating::create([
            'rating_id' => $rate_id,
            'user_id' => Auth::id(),
            'comment' => $request->input('comment'),
        ]);
        return $answerRating;
    }


    public function updateAnswer(Request $request, $answer_id)
    {
        // جلب الرد بناءً على answer_id
        $answerRating = Answer_Rating::findOrFail($answer_id);

        // التحقق من أن المستخدم هو صاحب الرد
        if ($answerRating->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // تحديث الرد
        $answerRating->update($request->all());

        return $answerRating;
    }

    public function deleteAnswer($answer_id)
    {
        // جلب الرد بناءً على answer_id
        $answerRating = Answer_Rating::findOrFail($answer_id);

        // التحقق من أن المستخدم هو صاحب الرد
        if ($answerRating->user_id != Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // حذف الرد
        $answerRating->delete();

        return response()->json(['message' => 'Answer deleted successfully'], 200);
    }

    public function getAnswersByRating($rate_id)
    {
        $user_id = auth()->user()->id; // تحصل على معرف المستخدم الحالي

        // جلب جميع الردود بناءً على معرّف التقييم ومعرّف المستخدم
        $answers = Answer_Rating::where('rating_id', $rate_id)
                                ->where('user_id', $user_id)
                                ->get();

        if ($answers->isEmpty()) {
            return response()->json(['message' => 'No answers found for this rating or you are not the owner'], 404);
        }

        return $answers;
    }

}
