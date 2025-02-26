<?php

namespace App\Http\Controllers\Vendor;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Services\AnswerRating\AnswerRatingService;
use App\Http\Requests\AnswerRating\StoreAnswerRatingRequest;
use App\Http\Requests\AnswerRating\UpdateAnswerRatingRequest;


class AnswerRatingController extends Controller
{
    protected $answerRatingService;

    public function __construct(AnswerRatingService $answerRatingService)
    {
        $this->answerRatingService = $answerRatingService;
    }

    public function store(StoreAnswerRatingRequest $request, $rate_id)
    {
        $response = $this->answerRatingService->storeAnswer($request, $rate_id);

        if ($response instanceof \Illuminate\Http\JsonResponse && $response->getStatusCode() !== 201) {
            return $response;
        }

        return response()->json($response, 201);
    }
    public function update(UpdateAnswerRatingRequest $request, $answer_id)
    {
        $response = $this->answerRatingService->updateAnswer($request, $answer_id);

        if ($response instanceof \Illuminate\Http\JsonResponse && $response->getStatusCode() !== 200) {
            return $response;
        }

        return response()->json($response, 200);
    }

    public function destroy($answer_id)
    {
        $response = $this->answerRatingService->deleteAnswer($answer_id);

        return $response;
    }

    public function getAnswersByRating($rate_id)
    {
        $response = $this->answerRatingService->getAnswersByRating($rate_id);

        if ($response instanceof \Illuminate\Http\JsonResponse && $response->getStatusCode() !== 200) {
            return $response;
        }

        return response()->json($response, 200);
    }
}
