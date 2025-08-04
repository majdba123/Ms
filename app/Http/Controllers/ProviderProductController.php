<?php

namespace App\Http\Controllers;

use App\Models\Order_Product;
use App\Models\Product;
use App\Models\Profile;
use App\Models\Provider_Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProviderProductController extends Controller
{
    public function getVendorOrders($vendor_id = null)
    {
        if ($vendor_id) {
            $vendor = Provider_Product::findOrFail($vendor_id);
        } else {
            $user_id = Auth::user();
            $vendor = Provider_Product::findOrFail($user_id->Provider_Product->id);
        }

        // الحصول على الطلبات مع العلاقات بما فيها صور المنتج
        $orders = $vendor->orders()
            ->with([
                'order:id,note,delivery_fee,user_id,status,created_at',
                'product:id,name',
                'product.images' // إضافة العلاقة لصور المنتج
            ])
            ->get();

        // تجميع العناصر حسب order_id
        $groupedOrders = $orders->groupBy('order_id')->map(function ($items) {
            return [
                'order_id' => $items->first()->order_id,
                'order_details' => $items->first()->order,
                'products' => $items->map(function ($item) {
                    return [
                        'order_product_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'total_price' => $item->total_price,
                        'quantity' => $item->quantity,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'product_images' => $item->product->images->map(function($image) {
                            return [
                                'image_id' => $image->id,
                                'image_url' => $image->imag // افترض أن هناك حقل 'url' في جدول الصور
                            ];
                        })
                    ];
                })
            ];
        })->values();

        return response()->json(['orders' => $groupedOrders], 200);
    }

   public function getVendorOrdersByStatus(Request $request)
    {
        $request->validate([
            'status' => 'required|string|in:pending,complete,cancelled,on_way,accepted',
        ]);

        $status = $request->status;
        $user_id = Auth::user();
        $vendor = Provider_Product::findOrFail($user_id->Provider_Product->id);

        // جلب الطلبات مع الفلترة حسب الحالة وإضافة العلاقة لصور المنتج
        $orders = $vendor->orders()
            ->where('status', $status)
            ->with([
                'order:id,delivery_fee,user_id,note,status,created_at',
                'product:id,name',
                'product.images' // إضافة العلاقة لصور المنتج
            ])
            ->get();

        // تجميع العناصر حسب order_id مع إضافة بيانات الصور
        $groupedOrders = $orders->groupBy('order_id')->map(function ($items) {
            return [
                'order_id' => $items->first()->order_id,
                'order_details' => $items->first()->order,
                'products' => $items->map(function ($item) {
                    return [
                        'order_product_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'total_price' => $item->total_price,
                        'quantity' => $item->quantity,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'product_images' => $item->product->images->map(function($image) {
                            return [
                                'image_id' => $image->id,
                                'image_url' => $image->imag // تعديل هذا الحقل حسب هيكل جدول الصور لديك
                            ];
                        })
                    ];
                })
            ];
        })->values();

        return response()->json(['orders' => $groupedOrders], 200);
    }

    public function getOrdersByProductId($id)
    {
        // جلب المستخدم الحالي
        $user = Auth::user();

        // التحقق من وجود التاجر المرتبط بالمستخدم
        if (!$user || !$user->Provider_Product) {
            return response()->json(['error' => 'Vendor not found for the current user.'], 403);
        }

        // جلب مزود المنتجات المرتبط بالمستخدم
        $providerProduct = $user->Provider_Product;

        // البحث عن المنتج
        $product = Product::where('id', $id)
            ->where('providerable_id', $providerProduct->id)
            ->where('providerable_type', Provider_Product::class)
            ->with('images') // تحميل صور المنتج مسبقاً
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Product not found or does not belong to this provider.'], 404);
        }

        // جلب الطلبات المتعلقة بالمنتج مع العلاقات
        $orders = $product->order_product()
            ->with([
                'order:id,note,user_id,status,created_at', // تم إصلاح الفاصلة الزائدة هنا
                'product:id,name',
                'product.images'
            ])
            ->get();

        $groupedOrders = $orders->groupBy('order_id')->map(function ($items) {
            return [
                'order_id' => $items->first()->order_id,
                'order_details' => $items->first()->order,
                'products' => $items->map(function ($item) {
                    return [
                        'order_product_id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'total_price' => $item->total_price,
                        'quantity' => $item->quantity,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'product_images' => $item->product->images->map(function($image) {
                            return [
                                'image_id' => $image->id,
                                'image_url' => $image->imag // تأكد من أن هذا الحقل موجود في جدول الصور
                            ];
                        })
                    ];
                })
            ];
        })->values();

        return response()->json([
            'orders' => $groupedOrders,
            'product_info' => [
                'id' => $product->id,
                'name' => $product->name,
                'images' => $product->images->map(function($image) {
                    return [
                        'image_id' => $image->id,
                        'image_url' => $image->url
                    ];
                })
            ]
        ], 200);
    }



    public function getVendorOrdersByOrderProductStatus(Request $request, $user_id, $product_id = null)
    {
        // التحقق من صحة الإدخال (status)
        $request->validate([
            'status' => 'nullable|string|in:pending,complete,cancelled', // القيم المسموح بها
        ]);

        $status = $request->input('status'); // قراءة حالة الطلب إذا تم إرسالها

        // جلب المستخدم الحالي
        $user = Auth::user();

        // التحقق من وجود التاجر المرتبط بالمستخدم
        if (!$user || !$user->Provider_Product) {
            return response()->json(['error' => 'Vendor not found for the current user.'], 403);
        }

        // جلب التاجر المرتبط
        $vendor = $user->Provider_Product;

        // البحث عن الطلبات الخاصة بـ user_id والتي ترتبط بمنتجات التاجر
        $ordersQuery = Order_Product::whereHas('order', function ($query) use ($user_id) {
                $query->where('user_id', $user_id); // الطلبات الخاصة بـ user_id
            })->whereHas('product', function ($query) use ($vendor) {
                $query->where('providerable_type', Provider_Product::class) // المنتجات الخاصة بمزود المنتجات فقط
                    ->where('providerable_id', $vendor->id); // التحقق من ID المزود
            });

        // إذا تم إرسال product_id يتم تصفية الطلبات بناءً عليه
        if ($product_id) {
            $ordersQuery->where('product_id', $product_id);
        }

        // إذا تم إرسال حالة status يتم تصفية الطلبات بناءً عليها
        if ($status) {
            $ordersQuery->where('status', $status);
        }

        // جلب الطلبات مع تضمين العلاقات المطلوبة بما فيها صور المنتج
        $orders = $ordersQuery
            ->with([
                'order:id,user_id,note,status,created_at',
                'product:id,name',
                'product.images' // إضافة العلاقة لصور المنتج
            ])
            ->get();

        // إذا لم يتم العثور على الطلبات
        if ($orders->isEmpty()) {
            return response()->json(['message' => 'No orders found for the specified criteria.'], 404);
        }

        // تعديل هيكل الاستجابة لتشمل صور المنتج
        $formattedOrders = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'product_id' => $order->product_id,
                'product_name' => $order->product->name,
                'total_price' => $order->total_price,
                'quantity' => $order->quantity,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'order_details' => $order->order,
                'product_images' => $order->product->images->map(function($image) {
                    return [
                        'image_id' => $image->id,
                        'image_url' => $image->imag // تأكد من أن هذا الحقل موجود في جدول الصور
                    ];
                })
            ];
        });

        return response()->json(['orders' => $formattedOrders], 200);
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
                    'national_id' => $user->national_id ?? 'N/A', // إضافة الرقم القومي هنا
                    'image_national_id' => $user->image_path ?? 'N/A', // إضافة الرقم القومي هنا
                    'lang' => $user->lang ?? 'N/A',
                    'lat' => $user->lat ?? 'N/A',
                    'type' => $user->type ?? 'N/A',
                    'user_status' => $user->status ?? 'N/A',
                    'status' => $x ?? 'N/A',

                ],
                'profile' => [

                    'image' => $user->Profile->image ?? 'N/A',
                    'address' => $user->Profile->address ?? 'N/A',
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

        // معالجة رفع الصورة بنفس طريقة ProfileService
        if ($request->hasFile('image')) {
            $shouldUpdateProfile = true;
            $imageFile = $request->file('image');

            // حذف الصورة القديمة إن وجدت
            if ($user->Profile && $user->Profile->image) {
                $this->deleteOldImage($user->Profile->image);
            }

            // حفظ الصورة الجديدة بنفس الطريقة
            $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
            $imagePath = 'profile_images/' . $imageName;
            Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
            $profileData['image'] = url('api/storage/' . $imagePath);
        } elseif ($request->has('image') && is_null($request->image)) {
            // إذا كانت قيمة الصورة null (طلب حذف الصورة)
            if ($user->Profile && $user->Profile->image) {
                $this->deleteOldImage($user->Profile->image);
                $profileData['image'] = null;
                $shouldUpdateProfile = true;
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
                'lat' => $user->lat ?? null,
                'lang' => $user->lang ?? null
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
