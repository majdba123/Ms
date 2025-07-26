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
            'id' => $vendor->id ?? null,
            'status' => $vendor->status ?? null,
            'type' => $vendor->user->type ?? null,
            'image' => $vendor->user->Profile->image ?? null,
            'address' => $vendor->user->Profile->address ?? null,
            'lang' => $vendor->user->lang ?? null,
            'lat' => $vendor->user->lat ?? null,
        ],
        'user' => [
            'id' => $user ? ($user->id ?? null) : ($vendor->user->id ?? null),
            'name' => $user ? ($user->name ?? null) : ($vendor->user->name ?? null),
            'email' => $user ? ($user->email ?? null) : ($vendor->user->email ?? null),
            'national_id' => $user ? ($user->national_id ?? null) : ($vendor->user->national_id ?? null),
            'image_national_id' => $user ? ($user->image_path ?? null) : ($vendor->user->image_path ?? null),
        ],
        'message' => $message ?: null,
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
