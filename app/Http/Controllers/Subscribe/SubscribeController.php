<?php

namespace App\Http\Controllers\Subscribe;
use App\Services\registartion\login; // Ensure the namespace is correct
use App\Http\Requests\registartion\LoginRequest ; // Ensure the namespace is correct
use App\Http\Requests\Subscribe\StoreSubscribeRequest;
use App\Services\Subscribe\SubscribeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

use App\Services\Subscribe\WebSubService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class SubscribeController extends Controller
{
    protected $subscribeService;
    protected $webSubService;
    public function __construct(SubscribeService $subscribeService , webSubService $webSubService)
    {
        $this->subscribeService = $subscribeService;
        $this->webSubService = $webSubService;

    }




    public function store(StoreSubscribeRequest $request): JsonResponse
    {
        $subscription = $this->subscribeService->createSubscription($request->validated());

        return response()->json(['message' => 'Subscription created successfully', 'subscription' => $subscription], 201);
    }


    public function getAllWebSubs(): JsonResponse
    {
        $webSubs = $this->webSubService->getAllWebSubs();

        return response()->json($webSubs);
    }





    public function my_subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,refuse,active,finished'
        ], [
            'status.required' => 'The status field is required.',
            'status.in' => 'The status must be one of the following: pending, active, finished.'
        ]);

        // إذا فشل التحقق، ارجع رسالة خطأ
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $status = $request->input('status', 'pending'); // القيمة الافتراضية هي pending

        try {
            $subscriptions = $this->subscribeService->getUserSubscriptionsByStatus($status);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json($subscriptions);
    }
}
