<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Subscribe\StoreWebSubRequest ; // Ensure the namespace is correct
use App\Models\WebSub;
use Illuminate\Http\JsonResponse;
use App\Services\Subscribe\WebSubService;
use App\Services\Subscribe\SubscribeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;



class DashboardController extends Controller
{
    protected $subscribeService;
    protected $webSubService;
    public function __construct(SubscribeService $subscribeService ,WebSubService $webSubService)
    {
        $this->subscribeService = $subscribeService;
        $this->webSubService = $webSubService;
    }



    public function getSubscriptionsByStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,active,finished'
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
            $subscriptions = $this->subscribeService->getSubscriptionsByStatus($status);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json($subscriptions);
    }



    public function storeWebSub(StoreWebSubRequest $request): JsonResponse
    {
        $webSub = $this->webSubService->createWebSub($request->validated());

        return response()->json(['message' => 'WebSub created successfully', 'webSub' => $webSub], 201);
    }



    public function updateWebSub(Request $request, $id): JsonResponse
    {
        $webSub = WebSub::findOrFail($id);

        $this->validate($request, [
            'time' => 'sometimes|integer|min:1|unique:web_subs,time,' . $webSub->id,
            'price' => 'sometimes|numeric|min:0',
        ]);
        $webSub = $this->webSubService->updateWebSub($webSub, $request->all());

        return response()->json(['message' => 'WebSub updated successfully', 'webSub' => $webSub]);
    }


    public function getAllWebSubs(): JsonResponse
    {
        $webSubs = $this->webSubService->getAllWebSubs();

        return response()->json($webSubs);
    }



    public function updateSubscriptionStatus(Request $request , $subscriptionId) : JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,refuse'
        ], [
            'status.required' => 'The status field is required.',
            'status.in' => 'The status must be one of the following: pending, active, finished.'
        ]);

        // إذا فشل التحقق، ارجع رسالة خطأ
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $status = $request->input('status');

        try {
            $subscription = $this->subscribeService->updateSubscriptionStatus($subscriptionId, $status);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json(['message' => 'Subscription status updated to active.', 'subscription' => $subscription]);
    }
}
