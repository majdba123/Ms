<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Http\Requests\Profile\StoreProfileRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UpdateUserInfoRequest;
use App\Models\User;
use App\Models\Provider_Service;
use App\Models\Provider_Product;
use App\Models\Driver;
use App\Models\Favourite_user;
use App\Models\Answer_Rating;
use App\Models\WebSub;
use App\Models\Rseevation;
use App\Models\UserNotification;
use App\Models\User_Plan;
use App\Models\Payment_Plan;
use App\Models\Cars;
use App\Services\Profile\ProfileService;
use Illuminate\Support\Facades\DB;
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

    public function updateProfile(UpdateProfileRequest $request , $user_id=null)
    {
        if($user_id != null)
        {
            $user = User::find($user_id);
        }else{
            $user = Auth::user();
        }

        $profile = $this->profileService->updateProfile($user, $request->all());

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        return response()->json(['message' => 'Profile updated successfully', 'profile' => $profile], 200);
    }

public function UpdateInfo(UpdateUserInfoRequest $request , $user_id=null)
{
        if($user_id != null)
        {
            $user = User::find($user_id);
        }else{
            $user = Auth::user();
        }

    if ($request->has('name')) {
        $user->name = $request->name;
    }
        if ($request->has('phone')) {
        $user->phone = $request->phone;
    }
        if ($request->has('lat')) {
        $user->lat = $request->lat;
    }
        if ($request->has('lang')) {
        $user->lang = $request->lang;
    }

    if ($request->has('national_id')) {
        // التحقق من أن الرقم القومي يتكون من 14 رقمًا
        if (strlen($request->national_id) !== 14 || !ctype_digit($request->national_id)) {
            return response()->json(['message' => 'الرقم القومي يجب أن يتكون من 14 رقمًا'], 400);
        }

        // التحقق من أن الرقم القومي غير مستخدم من قبل مستخدم آخر
        $existingUser = User::where('national_id', $request->national_id)
                            ->where('id', '!=', $user->id)
                            ->first();

        if ($existingUser) {
            return response()->json(['message' => 'الرقم القومي مسجل بالفعل لمستخدم آخر'], 400);
        }

        $user->national_id = $request->national_id;
    }

    if ($request->has('password')) {
        // التحقق من صحة كلمة المرور الحالية
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'كلمة المرور الحالية غير صحيحة'], 400);
        }
        // تحديث كلمة المرور الجديدة
        $user->password = Hash::make($request->password);
    }

    $user->save();
    return response()->json(['message' => 'تم تحديث معلومات المستخدم بنجاح', 'user' => $user], 200);
}

    public function getUserInfo( $user_id=null)
    {
        if($user_id != null)
        {
            $user = User::find($user_id);
        }else{
            $user = Auth::user();
        }

        $profile = $user->Profile;

        $userInfo = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name ?? 'N/A',
                'email' => $user->email ?? 'N/A',
                'phone' => $user->phone ?? 'N/A',
                'national_id' => $user->national_id ?? 'N/A', // إضافة الرقم القومي
                'national_id_image' => $user->image_path ?? 'N/A', // إضافة الرقم القومي
                'lang' => $user->lang ?? 'N/A',
                'lat' => $user->lat ?? 'N/A',

                // أي معلومات إضافية أخرى من نموذج User
            ],
            'profile' => [
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
                'national_id' => $user->national_id ?? 'N/A', // إضافة الرقم القومي
                'national_id_image' => $user->image_path ?? 'N/A', // إضافة الرقم القومي
                                'lang' => $user->lang ?? 'N/A',
                'lat' => $user->lat ?? 'N/A',

                // أي معلومات إضافية أخرى من نموذج User
            ],
            'profile' => [
                'image' => $profile->image ?? 'N/A',
                'address' => $profile->address ?? 'N/A',
                // أي معلومات إضافية أخرى من نموذج Profile
            ]
        ];

        return response()->json(['user_info' => $userInfo], 200);
    }


    public function getAllUsers(Request $request)
    {
        // بناء الاستعلام الأساسي
        $query = User::query();

        // تطبيق الفلاتر إذا وجدت في الطلب
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        if ($request->has('email')) {
            $query->where('email', 'like', '%' . $request->email . '%');
        }

        if ($request->has('national_id')) {
            $query->where('national_id', 'like', '%' . $request->national_id . '%');
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('phone')) {
            $query->where('phone', 'like', '%' . $request->phone . '%');
        }

        // جلب النتائج مع العلاقات (مثل Profile)
        $users = $query->with('Profile')->paginate($request->per_page ?? 15);

        // تنسيق النتيجة
        $formattedUsers = $users->map(function ($user) {
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? 'N/A',
                    'email' => $user->email ?? 'N/A',
                    'phone' => $user->phone ?? 'N/A',
                    'status' => $user->status ?? 'N/A',
                    'otp' => $user->otp ?? 'N/A',
                    'national_id' => $user->national_id ?? 'N/A',
                    'national_id_image' => $user->image_path ?? 'N/A',
                    'type' => $user->type ?? 'N/A',
                    'lang' => $user->lang ?? 'N/A',
                   'lat' => $user->lat ?? 'N/A',
                ],
                'profile' => [
                    'image' => $user->profile->image ?? 'N/A',
                    'address' => $user->profile->address ?? 'N/A',
                ] ?? null
            ];
        });

        return response()->json([
            'users' => $formattedUsers,
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem()
            ]
        ], 200);
    }

    public function updateUserStatus(Request $request, $user_id)
    {
        $request->validate([
            'status' => 'required|in:active,pand',
        ]);

        $user = User::find($user_id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->status = $request->status;
        $user->save();

        return response()->json([
            'message' => 'User status updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status
            ]
        ], 200);
    }


    public function deleteUserWithRelations($userId)
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($userId);
            if ($user->type == 1) {
                return response()->json(['message' => 'this user is admin and not '], 400);
            }

                // 1. حذف البيانات حسب نوع المستخدم
            switch ($user->type) {
                case 'service_provider':
                    Provider_Service::where('user_id', $userId)->delete();
                    break;
                case 'product_provider':
                case 'food_provider':
                    Provider_Product::where('user_id', $userId)->delete();
                    break;
                case 'driver':
                    Driver::where('user_id', $userId)->delete();
                    break;
            }


    // العلاقات التي ترجع مجموعة (hasMany)
    $user->Provider_service()->delete();
    $user->Provider_Product()->delete();
    $user->Driver()->delete();
    $user->favourite_user()->delete();
    $user->answere()->delete();
    $user->websub()->delete();
    $user->reservation()->delete();
    $user->notification()->delete();

    // العلاقات التي ترجع نموذج مفرد (hasOne) - استخدام optional
    optional($user->Profile)->delete();

    $user->delete();

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم حذف المستخدم وجميع بياناته المرتبطة بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'فشل في حذف المستخدم',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}




