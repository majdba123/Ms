<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use Illuminate\Http\Request;
use App\Services\Driver\DriverServic;
use Illuminate\Support\Facades\Auth;

class DriverController extends Controller
{

    protected $driver;

    public function __construct(DriverServic $driver)
    {

        $this->driver = $driver;

    }



    public function update_driver_status(Request $request, $driver_id)
    {
        try {
            // Validate that the `status` field exists and meets the requirements
            $request->validate([
                'status' => 'required|string|in:active,pending,pand|max:255',
            ]);

            // Call the service to update the vendor's status
            $data = $this->driver->updateDriverStatus($driver_id, $request->status);

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


    public function getDriverByStatus(Request $request)
    {
        // استلام الحالة من الطلب، وإذا لم يتم تحديدها افتراضياً "all"
        $status = $request->input('status', 'all');
        // استدعاء الخدمة للحصول على البيانات
        $vendors = $this->driver->getVendorsByStatus($status);

        // إرجاع النتيجة
        return response()->json($vendors);
    }


    public function get_driver_info($vendorId)
    {
        try {
            // استدعاء الخدمة لجلب معلومات الـ Vendor
            $vendor = $this->driver->getVendorInfo($vendorId);

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


/*
    public function driver_dashboard( $driver_id = null)
    {

        if ($driver_id==null) {
            $user = Auth::user();
            // التحقق من وجود التاجر المرتبط بالمستخدم
            if (!$user || !$user->Driver) {
                return response()->json(['error' => 'Vendor not found for the current user.'], 403);
            }
            // جلب التاجر المرتبط
            $driver = $user->Driver;

        }else{
            $driver = Driver::findOrFail($driver_id);
        }

        return response()->json($this->driver->getDashboardData($driver));
    }


*/


}
