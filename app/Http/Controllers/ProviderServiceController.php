<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Profile;
use App\Models\Provider_Service;
use App\Models\Rseevation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class ProviderServiceController extends Controller
{
     public function getVendorOrders($vendor_id = null)
    {
        if ($vendor_id) {
            // إذا كان $vendor_id موجودًا، فهذا يعني أن الطلب من المسؤول
            $vendor = Provider_Service::findOrFail($vendor_id);
        } else {
            // إذا لم يكن $vendor_id موجودًا، فهذا يعني أن الطلب من التاجر
            $user_id = Auth::user();
            $vendor = Provider_Service::findOrFail($user_id->Provider_service->id);
        }

        $orders = $vendor->reservations()
            ->with(['product', 'user'])
            ->get();

        return response()->json(['reservation' => $orders], 200);
    }



    public function getVendorOrdersByStatus(Request $request)
    {
        // التحقق من أن status موجود في الطلب
        $request->validate([
            'status' => 'required|string|in:pending,complete,cancelled',
        ]);

        $status = $request->status;
        $user_id=Auth::user();
        $vendor=Provider_Service::findOrfail($user_id->Provider_service->id);
        // جلب الطلبات بناءً على الحالة
        $orders = $vendor->reservations()
            ->where('status', $status)
            ->with(['product', 'user'])
            ->get();

        return response()->json(['orders' => $orders], 200);
    }

    public function getOrdersByProductId($id)
    {
        // جلب المستخدم الحالي
        $user = Auth::user();

        // التحقق من وجود التاجر المرتبط بالمستخدم
        if (!$user || !$user->Provider_service) {
            return response()->json(['error' => 'Vendor not found for the current user.'], 403);
        }

        // جلب التاجر المرتبط
// جلب مزود المنتجات المرتبط بالمستخدم
        $providerProduct = $user->Provider_service;

        // البحث عن المنتج بناءً على ID المنتج ومعرف مزود المنتجات
        $product = Product::where('id', $id)
            ->where('providerable_id', $providerProduct->id)
            ->where('providerable_type', Provider_Service::class) // التأكد من أن المزود هو مزود منتجات وليس خدمة
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found or does not belong to this provider.'], 404);
        }

        // جلب الطلبات المتعلقة بالمنتج
        $orders = $product->reservation()
                    ->with(['product', 'user'])

            ->get();

        return response()->json(['orders' => $orders], 200);
    }










    public function getProfile(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل الدخول'
                ], 401);
            }

            $response = [
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name ?? 'N/A',
                        'email' => $user->email ?? 'N/A',
                        'phone' => $user->phone ?? 'N/A',
                        // أي معلومات إضافية أخرى من نموذج User
                    ],
                    'profile' => [
                        'lang' => $user->Profile->lang ?? 'N/A',
                        'lat' => $user->Profile->lat ?? 'N/A',
                        'image' => $user->Profile->image ?? 'N/A',
                        'address' => $user->Profile->address ?? 'N/A',
                        // أي معلومات إضافية أخرى من نموذج Profile
                    ]
                ]


            ];


            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب بيانات المستخدم: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث معلومات المستخدم
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير مسجل الدخول'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,'.$user->id,
                'phone' => 'sometimes|string|max:20',
                'lat' => 'nullable|numeric',
                'lang' => 'nullable|numeric',
                'address' => 'nullable|string|max:255',
                'password' => 'sometimes|string|min:8|confirmed',
                'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // تحديث بيانات المستخدم الأساسية
            $userData = $request->only(['name', 'email', 'phone']);

            if ($request->has('password')) {
                $userData['password'] = bcrypt($request->password);
            }

            $user->update($userData);

            // تحضير بيانات البروفايل
            $profileData = [];
            $shouldUpdateProfile = false;

            // إضافة الحقول المرسلة فقط
            $profileFields = ['lat', 'lang', 'address'];
            foreach ($profileFields as $field) {
                if ($request->has($field)) {
                    $profileData[$field] = $request->$field;
                    $shouldUpdateProfile = true;
                }
            }

            // معالجة رفع الصورة بنفس طريقة storeProfile
            if ($request->hasFile('image')) {
                $shouldUpdateProfile = true;

                // حذف الصورة القديمة إن وجدت
                if ($user->Profile && $user->Profile->image) {
                    $oldImagePath = str_replace(asset('storage/profile_image/'), '', $user->Profile->image);
                    Storage::disk('public')->delete('profile_image/' . $oldImagePath);
                }

                // حفظ الصورة الجديدة بنفس الطريقة
                $imageFile = $request->file('image');
                $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = 'profile_image/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
                $profileData['image'] = asset('storage/profile_image/' . $imageName);
            }

            // تحديث أو إنشاء البروفايل إذا كان هناك بيانات لتحديثها
            if ($shouldUpdateProfile) {
                if ($user->Profile) {
                    $user->Profile->update($profileData);
                } else {
                    // تعيين قيم افتراضية لجميع الحقول المطلوبة
                    $profileData['user_id'] = $user->id;
                    $profileData['lat'] = $profileData['lat'] ?? 0;
                    $profileData['lang'] = $profileData['lang'] ?? 0;
                    $profileData['address'] = $profileData['address'] ?? '';

                    Profile::create($profileData);
                    $user->load('Profile');
                }
            }

            // بناء الاستجابة
            $response = [
                'success' => true,
                'message' => 'تم تحديث البيانات بنجاح',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ]
            ];

            // إضافة بيانات البروفايل إذا كانت موجودة
            if ($user->Profile) {
                if ($user->Profile->lat !== null || $user->Profile->lang !== null) {
                    $response['data']['location'] = [
                        'lat' => $user->Profile->lat,
                        'lang' => $user->Profile->lang
                    ];
                }

                if (!empty($user->Profile->address)) {
                    $response['data']['address'] = $user->Profile->address;
                }

                if ($user->Profile->image) {
                    $response['data']['image'] = $user->Profile->image;
                }
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في تحديث البيانات: ' . $e->getMessage()
            ], 500);
        }
    }

}
