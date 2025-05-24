<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\Profile\StoreProfileRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateUserInfoRequest;

use App\Services\Profile\ProfileService;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function storeProfile(StoreProfileRequest $request)
    {
        $user = Auth::user();

        if ($user->Profile) {
            return response()->json(['message' => 'You already have a profile'], 400);
        }

        $profile = $this->profileService->storeProfile($user, $request->all());

        return response()->json(['message' => 'Profile created successfully', 'profile' => $profile], 201);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = Auth::user();
        $profile = $this->profileService->updateProfile($user, $request->all());

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        return response()->json(['message' => 'Profile updated successfully', 'profile' => $profile], 200);
    }

    public function UpdateInfo(UpdateUserInfoRequest $request)
    {

        $user = Auth::user();
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('password')) {
            // التحقق من صحة كلمة المرور الحالية
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['message' => 'Current password is incorrect'], 400);
            }
            // تحديث كلمة المرور الجديدة
            $user->password = Hash::make($request->password);
        }
        $user->save();
        return response()->json(['message' => 'User information updated successfully', 'user' => $user], 200);
    }


    public function getUserInfo()
    {
        $user = Auth::user();
        $profile = $user->Profile;

        $userInfo = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? 'N/A',
                'email' => $user->email ?? 'N/A',
                'phone' => $user->phone ?? 'N/A',
                // أي معلومات إضافية أخرى من نموذج User
            ],
            'profile' => [
                'lang' => $profile->lang ?? 'N/A',
                'lat' => $profile->lat ?? 'N/A',
                'image' => $profile->image ?? 'N/A',
                'address' => $profile->address ?? 'N/A',
                // أي معلومات إضافية أخرى من نموذج Profile
            ]
        ];

        return response()->json(['user_info' => $userInfo], 200);
    }


    public function user_info($id)
    {
        $user = User::find($id);
        $profile = $user->Profile;

        $userInfo = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? 'N/A',
                'email' => $user->email ?? 'N/A',
                'phone' => $user->phone ?? 'N/A',
                // أي معلومات إضافية أخرى من نموذج User
            ],
            'profile' => [
                'lang' => $profile->lang ?? 'N/A',
                'lat' => $profile->lat ?? 'N/A',
                'image' => $profile->image ?? 'N/A',
                'address' => $profile->address ?? 'N/A',
                // أي معلومات إضافية أخرى من نموذج Profile
            ]
        ];

        return response()->json(['user_info' => $userInfo], 200);
    }

}




