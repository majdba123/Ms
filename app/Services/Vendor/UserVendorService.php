<?php

namespace App\Services\Vendor;

use App\Models\User;
use App\Models\Provider_Product;
use Illuminate\Support\Facades\Hash;

class UserVendorService
{


    private function formatResponse($vendor, $user = null, $message = '', $additionalData = [])
    {
        $response = [
            'vendor' => [
                'id' => $vendor->id,
                'status' => $vendor->status,
            ],
            'user' => [
                'id' => $user ? $user->id : $vendor->user->id,
                'name' => $user ? $user->name : $vendor->user->name,
                'email' => $user ? $user->email : $vendor->user->email,
                'national_id' => $user ? $user->national_id : $vendor->user->national_id,


            ],
            'message' => $message,
        ];

        return array_merge($response, $additionalData);
    }

    public function getVendorInfo($vendorId)
    {
        $vendor = Provider_Product::findOrFail($vendorId);

        return $this->formatResponse($vendor, null, 'Vendor info retrieved successfully');
    }

    public function updateVendorAndUser($vendorId, array $data)
    {
        $vendor = Provider_Product::findOrFail($vendorId);
        $user = $vendor->user;

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if (isset($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $additionalData = [];
        if (isset($data['password'])) {
            $additionalData['password_updated'] = true;
        }

        return $this->formatResponse($vendor, $user, 'Vendor and user updated successfully', $additionalData);
    }

    public function updateVendorStatus($vendorId, $status)
    {
        $vendor = Provider_Product::findOrFail($vendorId);
        $vendor->status = $status;
        $vendor->save();

        return $this->formatResponse($vendor, null, 'Vendor status updated successfully');
    }

    public function getVendorsByStatus($status, $perPage = 5)
    {
        $query = $status === 'all' ? Provider_Product::query() : Provider_Product::where('status', $status);
        $vendors = $query->paginate($perPage);

        $formattedVendors = $vendors->map(function ($vendor) {
            return $this->formatResponse($vendor, $vendor->user);
        });

        return [
            'data' => $formattedVendors,
            'pagination' => [
                'current_page' => $vendors->currentPage(),
                'last_page' => $vendors->lastPage(),
                'per_page' => $vendors->perPage(),
                'total' => $vendors->total(),
            ],
            'message' => 'Vendors retrieved successfully',
        ];
    }
}
