<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vendor\CreateUserAndVendorRequest;
use App\Http\Requests\Vendor\UpdateUserAndVendorRequest;
use App\Models\Product;
use App\Models\Provider_Product;
use App\Models\Provider_Service;
use App\Rules\ProviderService;
use Illuminate\Http\JsonResponse;
use App\Services\Vendor\VendorDashboardService;

use App\Services\Vendor\UserVendorService;
use App\Services\Order\OrderService;
use App\Services\Order\RservationService;

use App\Services\Service_Provider\Provider_ser;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    protected $service;
    protected $order;
    protected $dashboardService;
    protected $Provider_ser;
    protected $reser;

    public function __construct(RservationService $reser ,Provider_ser $Provider_ser ,UserVendorService $service, OrderService $order,VendorDashboardService $dashboardService)
    {
        $this->service = $service;
        $this->order = $order;
        $this->dashboardService = $dashboardService;
        $this->Provider_ser = $Provider_ser;
        $this->reser = $reser;

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
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return a custom response for validation errors
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422); // HTTP status code 422 indicates an unprocessable entity
        }
    }



    public function update_P_S_Status(Request $request, $vendorId)
    {
        try {
            // Validate that the `status` field exists and meets the requirements
            $request->validate([
                'status' => 'required|string|in:active,pending,pand|max:255',
            ]);

            // Call the service to update the vendor's status
            $data = $this->Provider_ser->updateProviderProductStatus($vendorId, $request->status);

            return response()->json([
                'message' => $data['message'],
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


    public function get_P_S_ByStatus(Request $request)
    {
        // استلام الحالة من الطلب، وإذا لم يتم تحديدها افتراضياً "all"
        $status = $request->input('status', 'all');
        // استدعاء الخدمة للحصول على البيانات
        $vendors = $this->Provider_ser->getProviderProductsByStatus($status);

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



    public function get_P_S_Info($vendorId)
    {
        try {
            // استدعاء الخدمة لجلب معلومات الـ Vendor
            $vendor = $this->Provider_ser->getProviderProductInfo($vendorId);

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

#############################################################################################################################################
#############################################################################################################################################
#############################################################################################################################################


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


    public function getReserByStatus(Request $request)
    {
        try {
            // التحقق من صحة إدخال الحالة
            $request->validate([
                'status' => 'required|string|in:pending,cancelled,complete,all',
            ]);

            // استدعاء الخدمة لجلب الطلبات بناءً على الحالة
            $orders = $this->reser->getresersByStatus($request->status);

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


    public function getReserByPriceRange(Request $request)
    {
        try {
            // التحقق من صحة إدخالات النطاق السعري
            $request->validate([
                'min_price' => 'required|numeric|min:0',
                'max_price' => 'required|numeric|min:0',
            ]);

            // استدعاء الخدمة لجلب الطلبات
            $orders = $this->reser->getReserByPriceRange($request->min_price, $request->max_price);

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



    public function getreserByProduct($productId)
    {
        try {
            $orders = $this->reser->getreserByProduct($productId);

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


#############################################################################################################################################
#############################################################################################################################################
#############################################################################################################################################




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


    public function getresersByUser($userId)
    {
        try {
            $orders = $this->reser->getreseByUser($userId);

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
            if (!$user || !$user->Provider_Product) {
                return response()->json(['error' => 'Vendor not found for the current user.'], 403);
            }
            // جلب التاجر المرتبط
            $vendor = $user->Provider_Product;

        }else{
            $vendor = Provider_Product::findOrFail($vendor_id);
        }

        return response()->json($this->dashboardService->getDashboardData($vendor));
    }




        // للإدمن
    public function Provider_service_dash( $vendor_id = null)
    {

        if ($vendor_id==null) {
            $user = Auth::user();
            // التحقق من وجود التاجر المرتبط بالمستخدم
            if (!$user || !$user->Provider_service) {
                return response()->json(['error' => 'Vendor not found for the current user.'], 403);
            }
            // جلب التاجر المرتبط
            $vendor = $user->Provider_service;

        }else{
            $vendor = Provider_Service::findOrFail($vendor_id);
        }

        return response()->json($this->dashboardService->getDashboardData_service($vendor));
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
            ->with(['order:id,user_id,status,created_at', 'product:id,name'])
            ->get();

               $groupedOrders = $orders->groupBy('order_id')->map(function ($items) {
            return [
                'order_id' => $items->first()->order_id,
                'order_details' => $items->first()->order,
                'products' => $items->map(function ($item) {
                    return [
                        'order_product_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'total_price' => $item->total_price,
                        'quantity' => $item->quantity,
                        'status' => $item->status,
                        'created_at' => $item->created_at
                    ];
                })
            ];
        })->values();

        return response()->json(['orders' => $groupedOrders], 200);
    }

    public function getVendorResr($vendor_id = null)
    {
        if ($vendor_id) {
            // إذا كان $vendor_id موجودًا، فهذا يعني أن الطلب من المسؤول
            $vendor = Provider_Service::findOrFail($vendor_id);
        } else {
            // إذا لم يكن $vendor_id موجودًا، فهذا يعني أن الطلب من التاجر
            $user_id = Auth::user();
            $vendor = Provider_Service::findOrFail($user_id->vendor->id);
        }

        $orders = $vendor->reservations()
            ->with(['product:id,name,price,description,category_id'])
            ->get();

        return response()->json(['reservation' => $orders], 200);
    }


}
