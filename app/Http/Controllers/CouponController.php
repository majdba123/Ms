<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
class CouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::query();

        // Apply filters if they exist in the request
        if ($request->has('code')) {
            $query->where('code', 'like', '%' . $request->input('code') . '%');
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('order_id')) {
            $query->whereHas('orders', function($q) use ($request) {
                $q->where('id', $request->input('order_id'));
            });
        }

        if ($request->has('expired_at')) {
            if ($request->input('expired_at') === 'expired') {
                $query->where('expires_at', '<', now());
            } elseif ($request->input('expired_at') === 'active') {
                $query->where('expires_at', '>', now())
                      ->orWhereNull('expires_at');
            }
        }

        $coupons = $query->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Coupons retrieved successfully',
            'data' => $coupons
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons|max:50',
            'discount_percent' => 'required|numeric|min:0|max:100',
            'expires_at' => 'nullable|date',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = Coupon::STATUS_ACTIVE;

        $coupon = Coupon::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon created successfully',
            'data' => $coupon
        ], 201);
    }

    public function show(Coupon $coupon)
    {
        return response()->json([
            'success' => true,
            'message' => 'Coupon retrieved successfully',
            'data' => $coupon
        ]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50',
                      Rule::unique('coupons')->ignore($coupon->id)],
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'expires_at' => 'nullable|date',
        ]);

        $validated['user_id'] = Auth::id();

        $coupon->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Coupon updated successfully',
            'data' => $coupon
        ]);
    }

    public function destroy(Coupon $coupon)
    {

        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully',
            'data' => null
        ]);
    }

    public function update_status(Request $request, Coupon $coupon)
    {
        $request->validate([
            'status' => ['required', Rule::in([Coupon::STATUS_ACTIVE, Coupon::STATUS_INACTIVE])]
        ]);

        $coupon->update(['status' => $request->input('status')]);

        return response()->json([
            'success' => true,
            'message' => 'Coupon status updated successfully',
            'data' => $coupon
        ]);
    }


    public function checkStatus($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon not found',
                'data' => null
            ], 404);
        }

        $isActive = $coupon->status == Coupon::STATUS_ACTIVE &&
                   ($coupon->expires_at === null || $coupon->expires_at > now());

        return response()->json([
            'success' => true,
            'message' => 'Coupon status checked successfully',
            'data' => [
                'is_active' => $isActive,
                'coupon' => $coupon
            ]
        ]);
    }
}
