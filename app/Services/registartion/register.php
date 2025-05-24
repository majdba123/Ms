<?php

namespace App\Services\registartion;

use App\Models\User;
use App\Models\Driver;
use App\Models\Provider_Product; // Import your ProviderProduct model
use App\Models\Provider_Service; // Import your ProviderService model
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache; // Import Cache facade

class register
{
    /**
     * Register a new user and create related records based on user type.
     *
     * @param array $data
     * @return User
     */
    public function register(array $data): User
    {
        // تحقق من وجود البريد الإلكتروني أو رقم الهاتف
        if (isset($data['email'])) {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ];

            if (isset($data['phone'])) {
                $userData['phone'] = $data['phone'];
            }
        } elseif (isset($data['phone'])) {
            $userData = [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
            ];
        } else {
            throw new \Exception('يجب أن تحتوي البيانات إما على البريد الإلكتروني أو رقم الهاتف.');
        }

        // إضافة نوع المستخدم كنص بدلاً من رقم
        $typeNames = [
            0 => 'user',
            1 => 'product_provider',
            2 => 'service_provider',
            3 => 'driver'
        ];

        if (!isset($data['type']) || !array_key_exists($data['type'], $typeNames)) {
            throw new \InvalidArgumentException('نوع المستخدم غير صالح');
        }

        $userData['type'] = $typeNames[$data['type']];
        $user = User::create($userData);

        // إنشاء السجلات الإضافية حسب النوع
        switch ($data['type']) {
            case 1:
                Provider_Product::create(['user_id' => $user->id]);
                break;
            case 2:
                Provider_Service::create(['user_id' => $user->id]);
                break;
            case 3:
                Driver::create(['user_id' => $user->id]);
                break;
        }

        return $user;
    }



    public function verifyOtp(string $otp, User $user): bool
    {
        // Retrieve the OTP data from the cache using the authenticated user's ID
        $otpData = Cache::get('otp_' . $user->id);

        // Check if the OTP data exists in the cache
        if (!$otpData) {
            throw new \Exception('No OTP data found in cache.');
        }

        // Retrieve the OTP from the cache data
        $sessionOtp = $otpData['otp'];

        // Check if the OTP matches
        if ($otp !== $sessionOtp) {
            throw new \Exception('Invalid OTP.');
        }

        // If OTP is valid, update the user's otp_verified column
        $user->otp = 1; // Assuming the column name is otp_verified
        $user->save(); // Save the changes to the database

        // Clear the OTP data from the cache after successful verification
        Cache::forget('otp_' . $user->id);

        return true; // Return true if OTP verification is successful
    }
}
