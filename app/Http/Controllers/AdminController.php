<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vendor\CreateUserAndVendorRequest;
use App\Http\Requests\Vendor\UpdateUserAndVendorRequest;
use App\Models\Product;
use App\Models\Provider_Product;
use Illuminate\Http\JsonResponse;
use App\Services\Vendor\VendorDashboardService;

use App\Services\Vendor\UserVendorService;
use App\Services\Order\OrderService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    protected $service;
    protected $order;
    protected $dashboardService;

    public function __construct(UserVendorService $service, OrderService $order,VendorDashboardService $dashboardService)
    {
        $this->service = $service;
        $this->order = $order;
        $this->dashboardService = $dashboardService;
    }



    public function updateVendorStatus(Request $request, $vendorId)
    {
        try {
            // Validate that the `status` field exists and meets the requirements
            $request->validate([
                'status' => 'required|string|in:active,pending,pand|max:255',
            ]);

            // Call the service to update the vendor's status
            $data = $this->service->updateVendorStatus($vendorId, $request->status);

            return response()->json([
                'message' => $data['message'],
                'vendor_id' => $data['vendor_id'],
                'status' => $data['status'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return a custom response for validation errors
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422); // HTTP status code 422 indicates an unprocessable entity
        }
    }


    public function getVendorsByStatus(Request $request)
    {
        // استلام الحالة من الطلب، وإذا لم يتم تحديدها افتراضياً "all"
        $status = $request->input('status', 'all');
        // استدعاء الخدمة للحصول على البيانات
        $vendors = $this->service->getVendorsByStatus($status);

        // إرجاع النتيجة
        return response()->json($vendors);
    }



    public function getVendorInfo($vendorId)
    {
        try {
            // استدعاء الخدمة لجلب معلومات الـ Vendor
            $vendor = $this->service->getVendorInfo($vendorId);

            if (!$vendor) {
                // إذا لم يتم العثور على Vendor
                return response()->json([
                    'message' => 'Vendor not found.',
                ], 404);
            }

            // إرجاع استجابة JSON تحتوي على معلومات الـ Vendor
            return response()->json([
                'message' => 'Vendor information retrieved successfully.',
                'vendor' => $vendor,
            ]);
        } catch (\Exception $e) {
            // التعامل مع أي خطأ غير متوقع
            return response()->json([
                'message' => 'An error occurred while fetching vendor information.',
                'error' => $e->getMessage(),
            ], 500); // HTTP status code 500 يشير إلى خطأ داخلي في الخادم
        }
    }
    public function getOrdersByStatus(Request $request)
    {
        try {
            // التحقق من صحة إدخال الحالة
            $request->validate([
                'status' => 'required|string|in:pending,cancelled,complete,all',
            ]);

            // استدعاء الخدمة لجلب الطلبات بناءً على الحالة
            $orders = $this->order->getOrdersByStatus($request->status);

            return response()->json([
                'message' => 'Orders retrieved successfully.',
                'orders' => $orders,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching orders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrdersByPriceRange(Request $request)
    {
        try {
            // التحقق من صحة إدخالات النطاق السعري
            $request->validate([
                'min_price' => 'required|numeric|min:0',
                'max_price' => 'required|numeric|min:0',
            ]);

            // استدعاء الخدمة لجلب الطلبات
            $orders = $this->order->getOrdersByPriceRange($request->min_price, $request->max_price);

            return response()->json([
                'message' => 'Orders retrieved successfully.',
                'orders' => $orders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching orders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function getOrdersByProduct($productId)
    {
        try {
            $orders = $this->order->getOrdersByProduct($productId);

            return response()->json([
                'message' => 'Orders retrieved successfully.',
                'orders' => $orders,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching orders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function getOrdersByUser($userId)
    {
        try {
            $orders = $this->order->getOrdersByUser($userId);

            return response()->json([
                'message' => 'Orders retrieved successfully.',
                'orders' => $orders,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching orders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getOrdersByCategory($categoryId)
    {
        try {
            $orders = $this->order->getOrdersByCategory($categoryId);

            return response()->json([
                'message' => 'Orders retrieved successfully.',
                'orders' => $orders,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while fetching orders by category.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    // للإدمن
    public function VendorDashboard( $vendor_id = null)
    {

        if ($vendor_id==null) {
            $user = Auth::user();
            // التحقق من وجود التاجر المرتبط بالمستخدم
            if (!$user || !$user->vendor) {
                return response()->json(['error' => 'Vendor not found for the current user.'], 403);
            }
            // جلب التاجر المرتبط
            $vendor = $user->vendor;

        }else{
            $vendor = Provider_Product::findOrFail($vendor_id);
        }

        return response()->json($this->dashboardService->getDashboardData($vendor));
    }



    public function adminDashboard()
    {
        // التحقق من أن المستخدم إ

        return response()->json($this->dashboardService->getDashboardadmin());
    }

    public function getVendorOrders($vendor_id = null)
    {
        if ($vendor_id) {
            // إذا كان $vendor_id موجودًا، فهذا يعني أن الطلب من المسؤول
            $vendor = Provider_Product::findOrFail($vendor_id);
        } else {
            // إذا لم يكن $vendor_id موجودًا، فهذا يعني أن الطلب من التاجر
            $user_id = Auth::user();
            $vendor = Provider_Product::findOrFail($user_id->vendor->id);
        }

        $orders = $vendor->orders()
            ->with(['order:id,status,created_at', 'product:id,name'])
            ->get();

        return response()->json(['orders' => $orders], 200);
    }


}
