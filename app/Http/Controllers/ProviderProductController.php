<?php

namespace App\Http\Controllers;

use App\Models\Order_Product;
use App\Models\Product;
use App\Models\Provider_Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProviderProductController extends Controller
{
    public function getVendorOrders($vendor_id = null)
    {
        if ($vendor_id) {
            // إذا كان $vendor_id موجودًا، فهذا يعني أن الطلب من المسؤول
            $vendor = Provider_Product::findOrFail($vendor_id);
        } else {
            // إذا لم يكن $vendor_id موجودًا، فهذا يعني أن الطلب من التاجر
            $user_id = Auth::user();
            $vendor = Provider_Product::findOrFail($user_id->Provider_Product->id);
        }

        $orders = $vendor->orders()
            ->with(['order:id,status,created_at', 'product:id,name'])
            ->get();

        return response()->json(['orders' => $orders], 200);
    }



    public function getVendorOrdersByStatus(Request $request)
    {
        // التحقق من أن status موجود في الطلب
        $request->validate([
            'status' => 'required|string|in:pending,complete,cancelled',
        ]);

        $status = $request->status;
        $user_id=Auth::user();
        $vendor=Provider_Product::findOrfail($user_id->Provider_Product->id);
        // جلب الطلبات بناءً على الحالة
        $orders = $vendor->orders()
            ->where('status', $status)
            ->with(['order:id,status,created_at', 'product:id,name'])
            ->get();

        return response()->json(['orders' => $orders], 200);
    }

    public function getOrdersByProductId($id)
    {
        // جلب المستخدم الحالي
        $user = Auth::user();

        // التحقق من وجود التاجر المرتبط بالمستخدم
        if (!$user || !$user->Provider_Product) {
            return response()->json(['error' => 'Vendor not found for the current user.'], 403);
        }

        // جلب التاجر المرتبط
// جلب مزود المنتجات المرتبط بالمستخدم
        $providerProduct = $user->Provider_Product;

        // البحث عن المنتج بناءً على ID المنتج ومعرف مزود المنتجات
        $product = Product::where('id', $id)
            ->where('providerable_id', $providerProduct->id)
            ->where('providerable_type', Provider_Product::class) // التأكد من أن المزود هو مزود منتجات وليس خدمة
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found or does not belong to this provider.'], 404);
        }

        // جلب الطلبات المتعلقة بالمنتج
        $orders = $product->order_product()
            ->with(['order:id,status,created_at'])
            ->get();

        return response()->json(['orders' => $orders], 200);
    }



    public function getVendorOrdersByOrderProductStatus(Request $request, $user_id, $product_id = null)
    {
        // التحقق من صحة الإدخال (status)
        $request->validate([
            'status' => 'nullable|string|in:pending,complete,cancelled', // القيم المسموح بها
        ]);

        $status = $request->input('status'); // قراءة حالة الطلب إذا تم إرسالها

        // جلب المستخدم الحالي
        $user = Auth::user();

        // التحقق من وجود التاجر المرتبط بالمستخدم
        if (!$user || !$user->Provider_Product) {
            return response()->json(['error' => 'Vendor not found for the current user.'], 403);
        }

        // جلب التاجر المرتبط
        $vendor = $user->Provider_Product;

        // البحث عن الطلبات الخاصة بـ user_id والتي ترتبط بمنتجات التاجر
        $ordersQuery = Order_Product::whereHas('order', function ($query) use ($user_id) {
                $query->where('user_id', $user_id); // الطلبات الخاصة بـ user_id
            })->whereHas('product', function ($query) use ($vendor) {
                $query->where('providerable_type', Provider_Product::class) // المنتجات الخاصة بمزود المنتجات فقط
                    ->where('providerable_id', $vendor->id); // التحقق من ID المزود
            });


        // إذا تم إرسال product_id يتم تصفية الطلبات بناءً عليه
        if ($product_id) {
            $ordersQuery->where('product_id', $product_id);
        }

        // إذا تم إرسال حالة status يتم تصفية الطلبات بناءً عليها
        if ($status) {
            $ordersQuery->where('status', $status);
        }

        // جلب الطلبات مع تضمين العلاقات المطلوبة
        $orders = $ordersQuery
            ->with(['order:id,user_id,status,created_at', 'product:id,name'])
            ->get();

        // إذا لم يتم العثور على الطلبات
        if ($orders->isEmpty()) {
            return response()->json(['message' => 'No orders found for the specified criteria.'], 404);
        }

        return response()->json(['orders' => $orders], 200);
    }

}
