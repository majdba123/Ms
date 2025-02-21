<?php

namespace App\Http\Controllers\Registration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\registartion\RegisterUser ; // Ensure the namespace is correct
use App\Services\registartion\register; // Ensure the namespace is correct
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache; // Import Cache facade
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    protected $userService;

    public function __construct(register $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Handle the registration of a new user.
     *
     * @param RegisterUser   $request
     * @return JsonResponse
     */
    public function register(RegisterUser $request): JsonResponse
    {
        $validatedData = $request->validated();

        // Pass the modified request data to the service
        $user = $this->userService->register($validatedData);

        $this->sendOtp($user->id);

        return response()->json([
            'message' => 'User  registered successfully',
            'user' => $user,
        ], 201);
    }

    private function sendOtp($id)
    {
        $user = User::findOrFail($id);

        $otp = Str::random(6);
        // Store OTP in cache with ID and email, set expiration time (e.g., 5 minutes)
        Cache::put('otp_' . $user->id, ['otp' => $otp, 'email' => $user->email], 300);

        // Send OTP via email
        Mail::to($user->email)->send(new SendOtpMail($otp));
        return $otp;
    }


    public function verfication_otp(Request $request)
    {
        // Validate the request
        $request->validate([
            'otp' => 'required|string|size:6', // Ensure OTP is a string of size 6
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Check if the user is authenticated
        if (!$user) {
            return response()->json(['message' => 'User  not authenticated.'], 401);
        }

        try {
            // Call the verifyOtp method from the service
            $this->userService->verifyOtp($request->input('otp'), $user);

            return response()->json(['message' => 'OTP verified successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
