<?php

namespace App\Http\Controllers\Registration;
use Laravel\Socialite\Facades\Socialite; // Add this line
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }



    public function callback()
    {
        $googleUser  = Socialite::driver('google')->user();

        // Check if the user already exists
        $user = User::where('email', $googleUser ->getEmail())->first();

        if (!$user) {
            // Create a new user if not found
            $user = User::create([
                'name' => $googleUser ->getName(),
                'email' => $googleUser ->getEmail(),
                'password' => bcrypt(Str::random(16)), // Use Str::random instead of str_random
            ]);
        }

        // Log the user in
        Auth::login($user, true);

        return response()->json(['message' => 'User  Register in successfully', 'user' => $user]);
    }
}
