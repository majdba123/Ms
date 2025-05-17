<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Provider_Product;
use App\Models\vendor;
use App\Services\Commission\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Product\ProductService;
use Illuminate\Support\Facades\Validator;

class CommissionController extends Controller
{

    protected $commissionService;

    public function __construct(CommissionService $commissionService)
    {
        $this->commissionService = $commissionService;
    }

    /**
     * حساب عمولة منتج معين
     */
    public function calculateByProduct(Request $request, $product_id)
    {

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:all,pending,complete,cancelled', // status مطلوب الآن
            'vendor_id' => 'nullable|exists:provider__products,id'

        ]);

        // التحقق من الأخطاء
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product = Product::findOrFail($product_id);
        $status = $request->status ?? 'complete';

        // تحديد التاجر (إما من الرابط أو من المستخدم المسجل)
        if ($request->has('vendor_id')) {
            // إذا كان هناك vendor_id في الرابط (طلب من الإدمن)
            $vendor = Provider_Product::findOrFail($request->vendor_id);
        } else {
            // إذا لم يكن هناك vendor_id (طلب من التاجر نفسه)
            $user = Auth::user();
            if (!$user->Provider_Product) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم ليس تاجراً'
                ], 403);
            }
            $vendor = $user->Provider_Product;
        }

        return $this->commissionService->calculateProductCommission($vendor, $product, $status);
    }


    public function getVendorCommission(Request $request, $vendor_id = null)
    {


        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:all,pending,complete,cancelled', // status مطلوب الآن
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // التحقق من الأخطاء
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($vendor_id) {
            $vendor = Provider_Product::findOrFail($vendor_id);
        } else {
            $user = Auth::user();
            $vendor = $user->vendor;
        }

        $commissionService = new CommissionService();
        $result = $commissionService->calculateVendorCommission(
            $vendor,
            $request->status, // status أصبح معامل مطلوب
            $request->start_date,
            $request->end_date
        );

        return response()->json($result, 200);
    }
}
