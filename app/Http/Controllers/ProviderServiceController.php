<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Profile;
use App\Models\Provider_Service;
use App\Models\Rseevation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\UserNotification;
use App\Events\PrivateNotification;
use Illuminate\Support\Facades\Log;

class ProviderServiceController extends Controller
{
    public function getVendorOrders($vendor_id = null)
    {
        if ($vendor_id) {
            $vendor = Provider_Service::findOrFail($vendor_id);
        } else {
            $user_id = Auth::user();
            $vendor = Provider_Service::findOrFail($user_id->Provider_service->id);
        }

        $orders = $vendor->reservations()
            ->with(['product.images', 'user'])
            ->paginate(10); // إضافة Pagination هنا

        return response()->json([
            'reservation' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ]
        ], 200);
    }

    public function getVendorOrdersByStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|string|in:pending,complete,cancelled',
        ]);

        $status = $request->status;
        $user_id = Auth::user();
        $vendor = Provider_Service::findOrfail($user_id->Provider_service->id);

        $orders = $vendor->reservations()
            ->where('status', $status)
            ->with(['product.images', 'user'])
            ->paginate(10); // إضافة Pagination هنا

        return response()->json([
            'orders' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ]
        ], 200);
    }

public function update_status_reservation(Request $request, $reser_id)
{
    $request->validate([
        'status' => 'required|string|in:pending,complete,cancelled',
    ]);

    $status = $request->status;
    $user_id = Auth::user();

    // التأكد من أن المستخدم لديه خدمة مزود
    if (!$user_id->Provider_service) {
        return response()->json(['error' => 'User is not a service provider'], 403);
    }

    $vendor = Provider_Service::findOrFail($user_id->Provider_service->id);

    // البحث عن الحجز مع معلومات المستخدم والمنتج
    $reservation = $vendor->reservations()
        ->with(['user', 'product'])
        ->where('rseevations.id', $reser_id)
        ->first();

    if (!$reservation) {
        return response()->json(['error' => 'Reservation not found'], 404);
    }

    DB::beginTransaction();
    try {
        // تحديث حالة الحجز
        $reservation->status = $status;
        $reservation->save();

        // محاولة إرسال الإشعار (مع معالجة الخطأ دون إيقاف العملية)
        try {
            $this->sendReservationStatusNotification($reservation, $status);
        } catch (\Exception $e) {
            // تسجيل الخطأ في السجلات (Logs) دون إيقاف العملية
            Log::error("Failed to send reservation notification: " . $e->getMessage());
        }

        DB::commit();

        return response()->json([
            'message' => 'Reservation status updated successfully',
            'reservation' => $reservation
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to update reservation status',
            'error' => $e->getMessage()
        ], 500);
    }
}









    protected function sendReservationStatusNotification($reservation, $status)
    {
        $user = $reservation->user;
        $product = $reservation->product;

        $statusMessages = [
            'complete' => 'تم إكمال حجزك للمنتج: ' . $product->name,
            'cancelled' => 'تم إلغاء حجزك للمنتج: ' . $product->name,
            'pending' => 'تم تحديث حالة حجزك للمنتج: ' . $product->name . ' إلى قيد الانتظار'
        ];

        $message = $statusMessages[$status] ?? 'تم تحديث حالة طلبك رقم #'.$reservation->id.' إلى '.$status;

        // إرسال الإشعار الفوري
        event(new PrivateNotification($user->id, $message));

        // تخزين الإشعار في قاعدة البيانات
        UserNotification::create([
            'user_id' => $user->id,
            'notification' => $message,

        ]);

    }










    public function getOrdersByProductId($id)
    {
        $user = Auth::user();

        if (!$user || !$user->Provider_service) {
            return response()->json(['error' => 'Vendor not found for the current user.'], 403);
        }

        $providerProduct = $user->Provider_service;

        $product = Product::where('id', $id)
            ->where('providerable_id', $providerProduct->id)
            ->where('providerable_type', Provider_Service::class)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found or does not belong to this provider.'], 404);
        }

        $orders = $product->reservation()
            ->with(['product.images', 'user'])
            ->paginate(10); // إضافة Pagination هنا

        return response()->json([
            'orders' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ]
        ], 200);
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

        if($user->Driver)
        {
            $x=$user->Driver->status;
        }elseif($user->Provider_Product)
        {
            $x=$user->Provider_Product->status;
        }else{
            $x=$user->Provider_service->status;
        }

        $response = [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name ?? 'N/A',
                    'email' => $user->email ?? 'N/A',
                    'phone' => $user->phone ?? 'N/A',
                    'national_id' => $user->national_id ?? 'N/A', // إضافة الرقم القومي
                    'image_national_id' => $user->image_path ?? 'N/A', // إضافة الرقم القومي هنا
                    'lang' => $user->lang ?? 'N/A',
                    'lat' => $user->lat ?? 'N/A',
                    'user_status' => $user->status ?? 'N/A',
                    'status' => $x ?? 'N/A',

                    // أي معلومات إضافية أخرى من نموذج User
                ],
                'profile' => [
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
            'phone' => 'sometimes|string|max:20|unique:users,phone,'.$user->id,
            'national_id' => [
                'sometimes',
                'string',
                'size:14',
                'unique:users,national_id,'.$user->id,
                function ($attribute, $value, $fail) {
                    if (!ctype_digit($value)) {
                        $fail('الرقم القومي يجب أن يحتوي على أرقام فقط');
                    }
                }
            ],
            'lat' => 'nullable|numeric',
            'lang' => 'nullable|numeric',
            'address' => 'nullable|string|max:255',
            'password' => 'sometimes|string|min:8|confirmed',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
        ], [
            'national_id.size' => 'الرقم القومي يجب أن يتكون من 14 رقمًا',
            'national_id.unique' => 'الرقم القومي مسجل بالفعل لمستخدم آخر'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // تحديث بيانات المستخدم الأساسية
        $userData = $request->only(['name', 'email', 'phone', 'national_id','lang','lat']);

        if ($request->has('password')) {
            $userData['password'] = bcrypt($request->password);
        }

        $user->update($userData);

        // تحضير بيانات البروفايل
        $profileData = [];
        $shouldUpdateProfile = false;

        // إضافة الحقول المرسلة فقط
        $profileFields = ['address'];
        foreach ($profileFields as $field) {
            if ($request->has($field)) {
                $profileData[$field] = $request->$field;
                $shouldUpdateProfile = true;
            }
        }

        // معالجة الصورة بنفس طريقة ProfileService
        if ($request->has('image')) {
            $shouldUpdateProfile = true;

            // إذا كانت الصورة ملف جديد
            if ($request->image instanceof \Illuminate\Http\UploadedFile) {
                // حذف الصورة القديمة إن وجدت
                if ($user->Profile && $user->Profile->image) {
                    $this->deleteOldImage($user->Profile->image);
                }

                // حفظ الصورة الجديدة
                $imageFile = $request->file('image');
                $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = 'profile_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
                $profileData['image'] = url('api/storage/' . $imagePath);
            }
            // إذا كانت القيمة null (طلب حذف الصورة)
            elseif (is_null($request->image)) {
                if ($user->Profile && $user->Profile->image) {
                    $this->deleteOldImage($user->Profile->image);
                    $profileData['image'] = null;
                }
            }
        }

        // تحديث أو إنشاء البروفايل
        if ($shouldUpdateProfile) {
            if ($user->Profile) {
                $user->Profile->update($profileData);
            } else {
                $profileData['user_id'] = $user->id;
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
                'national_id' => $user->national_id,
            ]
        ];

        // إضافة بيانات البروفايل إذا كانت موجودة
        if ($user->Profile) {
            $response['data']['location'] = [
                'lat' => $user->Profile->lat ?? null,
                'lang' => $user->Profile->lang ?? null
            ];
            $response['data']['address'] = $user->Profile->address ?? null;
            $response['data']['image'] = $user->Profile->image ?? null;
        }

        return response()->json($response);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'فشل في تحديث البيانات: ' . $e->getMessage()
        ], 500);
    }
}

protected function deleteOldImage(string $imageUrl)
{
    try {
        $basePath = url('api/storage');
        $relativePath = str_replace($basePath, '', $imageUrl);
        $relativePath = ltrim($relativePath, '/');

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    } catch (\Exception $e) {
        Log::error("Failed to delete old profile image: " . $e->getMessage());
    }
}
}
