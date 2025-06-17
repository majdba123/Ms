<?php

namespace App\Services\Service_Provider;

use App\Models\User;
use App\Models\Provider_Service;
use Illuminate\Support\Facades\Hash;

class Provider_ser
{
private function formatResponse($providerProduct, $user = null, $message = '', $additionalData = [])
{
    $response = [
        'provider_Servive' => [
            'id' => $providerProduct->id ?? null,
            'status' => $providerProduct->status ?? null,
            'image' => $providerProduct->user->Profile->image ?? null,
            'address' => $providerProduct->user->Profile->address ?? null,
            'type' => $providerProduct->user->type ?? null,

            'lang' => $providerProduct->user->Profile->lang ?? null,
            'lat' => $providerProduct->user->Profile->lat ?? null,
        ],
        'user' => [
            'id' => $user ? ($user->id ?? null) : ($providerProduct->user->id ?? null),
            'name' => $user ? ($user->name ?? null) : ($providerProduct->user->name ?? null),
            'email' => $user ? ($user->email ?? null) : ($providerProduct->user->email ?? null),
            'national_id' => $user ? ($user->national_id ?? null) : ($providerProduct->user->national_id ?? null),
            'image_national_id' => $user ? ($user->image_path ?? null) : ($providerProduct->user->image_path ?? null),
        ],
        'message' => $message ?: null,
    ];

    return array_merge($response, $additionalData);
}

    public function getProviderProductInfo($providerProductId)
    {
        $providerProduct = Provider_Service::findOrFail($providerProductId);

        return $this->formatResponse($providerProduct, null, 'Provider product info retrieved successfully');
    }

    public function updateProviderProductAndUser($providerProductId, array $data)
    {
        $providerProduct = Provider_Service::findOrFail($providerProductId);
        $user = $providerProduct->user;

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

        return $this->formatResponse($providerProduct, $user, 'Provider product and user updated successfully', $additionalData);
    }

    public function updateProviderProductStatus($providerProductId, $status)
    {
        $providerProduct = Provider_Service::findOrFail($providerProductId);
        $providerProduct->status = $status;
        $providerProduct->save();

        return $this->formatResponse($providerProduct, null, 'Provider product status updated successfully');
    }

    public function getProviderProductsByStatus($status, $perPage = 5)
    {
        $query = $status === 'all' ? Provider_Service::query() : Provider_Service::where('status', $status);
        $providerProducts = $query->paginate($perPage);

        $formattedProviderProducts = $providerProducts->map(function ($providerProduct) {
            return $this->formatResponse($providerProduct, $providerProduct->user);
        });

        return [
            'data' => $formattedProviderProducts,
            'pagination' => [
                'current_page' => $providerProducts->currentPage(),
                'last_page' => $providerProducts->lastPage(),
                'per_page' => $providerProducts->perPage(),
                'total' => $providerProducts->total(),
            ],
            'message' => 'Provider products retrieved successfully',
        ];
    }

    // Additional methods specific to Provider_Product model

}
