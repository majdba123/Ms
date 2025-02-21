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
        // Create a new user instance
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        // Check the user type and create additional records if necessary
        switch ($data['type']) {
            case 0:
                // Type 0: Only create a user
                break;
            case 1:
                // Type 1: Create a user and a provider_product record
                Provider_Product::create([
                    'user_id' => $user->id,
                ]);
                break;
            case 2:
                // Type 2: Create a user and a provider_service record
                Provider_Service::create([
                    'user_id' => $user->id,
                    // Add other necessary fields for provider_service
                ]);
                break;
            case 3:
                // Type 3: Create a user and a driver record
                // Assuming you have a Driver model
                Driver::create([
                    'user_id' => $user->id,
                    // Add other necessary fields for driver
                ]);
                break;
            default:
                throw new \InvalidArgumentException('Invalid user type');
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
        $user->otp_verified = 1; // Assuming the column name is otp_verified
        $user->save(); // Save the changes to the database

        // Clear the OTP data from the cache after successful verification
        Cache::forget('otp_' . $user->id);

        return true; // Return true if OTP verification is successful
    }
}
