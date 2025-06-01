<?php

namespace App\Services\Driver;

use App\Models\Driver;
use Illuminate\Http\JsonResponse;

class DriverServic
{
    private function formatResponse($driver, $user = null, $message = '', $additionalData = [])
    {
        $response = [
            'driver' => [
                'id' => $driver->id,
                'status' => $driver->status,
            ],
            'user' => [
                'id' => $user ? $user->id : $driver->user->id,
                'name' => $user ? $user->name : $driver->user->name,
                'email' => $user ? $user->email : $driver->user->email,
                'image_national_id' => $user ? $user->image_path : $driver->user->image_path,
                'national_id' => $user ? $user->national_id : $driver->user->national_id,

            ],
            'message' => $message,
        ];

        return array_merge($response, $additionalData);
    }

    public function updateDriverStatus($driver_id, $status)
    {
        $driver = Driver::findOrFail($driver_id);
        $driver->status = $status;
        $driver->save();

        return $this->formatResponse($driver, null, 'Vendor status updated successfully');
    }


    public function getVendorsByStatus($status, $perPage = 5)
    {
        $query = $status === 'all' ? Driver::query() : Driver::where('status', $status);
        $drivers = $query->paginate($perPage);

        $formattedVendors = $drivers->map(function ($driver) {
            return $this->formatResponse($driver, $driver->user);
        });

        return [
            'data' => $formattedVendors,
            'pagination' => [
                'current_page' => $drivers->currentPage(),
                'last_page' => $drivers->lastPage(),
                'per_page' => $drivers->perPage(),
                'total' => $drivers->total(),
            ],
            'message' => 'drivers retrieved successfully',
        ];
    }


    public function getVendorInfo($driver_id)
    {
        $driver = Driver::findOrFail($driver_id);

        return $this->formatResponse($driver, null, 'driver info retrieved successfully');
    }


   /* public function getDashboardData(Driver $vendor)
    {
        return [
            'stats' => [
                'completed_orders' => $vendor->completed_orders_count,
                'pending_orders' => $vendor->pending_orders_count,
                'cancelled_orders' => $vendor->cancelled_orders_count,
                'total_sales_complete' => $vendor->total_sales,

            ],
        ];
    }*/


}
