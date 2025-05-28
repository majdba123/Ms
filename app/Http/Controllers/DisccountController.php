<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Discount\DiscountStoreRequest;
use App\Http\Requests\Discount\DiscountUpdateRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\Disccount;
use App\Helpers\checkActiveSubscription;

use Illuminate\Http\Request;

class DisccountController extends Controller
{
    public function store(DiscountStoreRequest $request, $product_id)
    {
        DB::beginTransaction();

        try {
            $product = Product::findOrFail($product_id);
            $user = Auth::user();

            $providerType = $request->is('api/service_provider*') ? 1 : 0;


            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            if ($providerType == 1) {
                $providerId = Auth::user()->Provider_service->id;

                // Verify ownership
                if ($product->providerable_id != $providerId || $product->providerable_type != 'App\\Models\\Provider_Service') {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }



            } else {
                $providerId = Auth::user()->Provider_Product->id;

                // Verify ownership
                if ($product->providerable_id != $providerId || $product->providerable_type != 'App\\Models\\Provider_Product') {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }


            }

            // Validate provider type and get provider info
            if ($providerType === 1) {
                if (!$user->Provider_service) {
                    return response()->json([
                        'success' => false,
                        'message' => 'مزود الخدمة غير موجود'
                    ], 404);
                }
                $providerId = $user->Provider_service->id;
                $providerTypeClass = 'App\\Models\\Provider_Service';
            } else {
                if (!$user->Provider_Product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'مزود المنتج غير موجود'
                    ], 404);
                }
                $providerId = $user->Provider_Product->id;
                $providerTypeClass = 'App\\Models\\Provider_Product';
            }

            // Check for existing active discount
            $existingActiveDiscount = Disccount::where('product_id', $product->id)
                ->where('status', 'active')
                ->exists();

            if ($existingActiveDiscount) {
                return response()->json([
                    'success' => false,
                    'message' => 'يوجد بالفعل خصم فعال لهذا المنتج',
                    'errors' => ['product_id' => ['لا يمكن إنشاء خصم جديد لمنتج لديه خصم فعال']]
                ], 422);
            }

            $discount = Disccount::create([
                'product_id' => $product->id,
                'providerable1_id' => $providerId,
                'providerable1_type' => $providerTypeClass,
                'value' => $request->value,
                'fromtime' => $request->from_time,
                'totime' => $request->to_time,
                'status' => 'active',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء الخصم بنجاح',
                'data' => $discount
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'فشل في إنشاء الخصم',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحديث خصم موجود
     */


    public function update(DiscountUpdateRequest $request, $product_id)
    {
        DB::beginTransaction();

        try {
            $product = Product::findOrFail($product_id);
            $user = Auth::user();

            $providerType = $request->is('api/service_provider*') ? 1 : 0;

            // Validate provider type and get provider info
            if ($providerType === 1) {
                if (!$user->Provider_service) {
                    return response()->json([
                        'success' => false,
                        'message' => 'مزود الخدمة غير موجود'
                    ], 404);
                }
                $providerId = $user->Provider_service->id;
                $providerTypeClass = 'App\\Models\\Provider_Service';

                // Verify ownership
                if ($product->providerable_id != $providerId || $product->providerable_type != $providerTypeClass) {
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بتحديث خصم لهذا المنتج'
                    ], 403);
                }
            } else {
                if (!$user->Provider_Product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'مزود المنتج غير موجود'
                    ], 404);
                }
                $providerId = $user->Provider_Product->id;
                $providerTypeClass = 'App\\Models\\Provider_Product';

                // Verify ownership
                if ($product->providerable_id != $providerId || $product->providerable_type != $providerTypeClass) {
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بتحديث خصم لهذا المنتج'
                    ], 403);
                }
            }

            // Check if discount exists
            if (!$product->discount) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد خصم لهذا المنتج',
                    'errors' => ['product_id' => ['لم يتم العثور على خصم لهذا المنتج']]
                ], 404);
            }

            // If activating discount, check for existing active discounts
            if ($request->has('status') && $request->status === 'active') {
                $existingActiveDiscount = Disccount::where('product_id', $product->id)
                    ->where('id', '!=', $product->discount->id)
                    ->where('status', 'active')
                    ->exists();

                if ($existingActiveDiscount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'يوجد بالفعل خصم فعال آخر لهذا المنتج',
                        'errors' => ['product_id' => ['لا يمكن تفعيل أكثر من خصم لنفس المنتج']]
                    ], 422);
                }
            }



            // Prepare update data
            $updateData = [];
            if ($request->has('value')) $updateData['value'] = $request->value;
            if ($request->has('from_time')) $updateData['fromtime'] = $request->from_time;
            if ($request->has('to_time')) $updateData['totime'] = $request->to_time;
            if ($request->has('status')) $updateData['status'] = $request->status;

            $discount = $product->discount()->update($updateData);
            $updatedDiscount = $product->discount->fresh();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث الخصم بنجاح',
                'data' => $updatedDiscount
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'المنتج غير موجود'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'فشل في تحديث الخصم',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * تغيير حالة الخصم (تفعيل/تعطيل)
     */
   public function changeStatus(Request $request, $product_id)
{
    DB::beginTransaction();

    try {
        $request->validate([
            'status' => 'required|in:active,inactive'
        ], [
            'status.required' => 'حقل حالة الخصم مطلوب',
            'status.in' => 'حالة الخصم يجب أن تكون إما active أو inactive',
        ]);

        $product = Product::findOrFail($product_id);
        $user = Auth::user();

        $providerType = $request->is('api/service_provider*') ? 1 : 0;

        // Validate provider type and get provider info
        if ($providerType === 1) {
            if (!$user->Provider_service) {
                return response()->json([
                    'success' => false,
                    'message' => 'مزود الخدمة غير موجود'
                ], 404);
            }
            $providerId = $user->Provider_service->id;
            $providerTypeClass = 'App\\Models\\Provider_Service';

            // Verify ownership
            if ($product->providerable_id != $providerId || $product->providerable_type != $providerTypeClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتغيير حالة خصم لهذا المنتج'
                ], 403);
            }
        } else {
            if (!$user->Provider_Product) {
                return response()->json([
                    'success' => false,
                    'message' => 'مزود المنتج غير موجود'
                ], 404);
            }
            $providerId = $user->Provider_Product->id;
            $providerTypeClass = 'App\\Models\\Provider_Product';

            // Verify ownership
            if ($product->providerable_id != $providerId || $product->providerable_type != $providerTypeClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بتغيير حالة خصم لهذا المنتج'
                ], 403);
            }
        }

        // Check if discount exists
        if (!$product->discount) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد خصم لهذا المنتج',
                'errors' => ['product_id' => ['لم يتم العثور على خصم لهذا المنتج']]
            ], 404);
        }

        // If activating discount, check for existing active discounts
        if ($request->status === 'active') {
            $existingActiveDiscount = Disccount::where('product_id', $product->id)
                ->where('id', '!=', $product->discount->id)
                ->where('status', 'active')
                ->exists();

            if ($existingActiveDiscount) {
                return response()->json([
                    'success' => false,
                    'message' => 'يوجد بالفعل خصم فعال لهذا المنتج',
                    'errors' => ['product_id' => ['لا يمكن تفعيل أكثر من خصم لنفس المنتج']]
                ], 422);
            }
        }

        $product->discount()->update(['status' => $request->status]);
        $updatedDiscount = $product->discount->fresh();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => $request->status === 'active'
                ? 'تم تفعيل الخصم بنجاح'
                : 'تم تعطيل الخصم بنجاح',
            'data' => $updatedDiscount
        ], 200);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'المنتج غير موجود'
        ], 404);

    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'خطأ في التحقق',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'فشل في تغيير حالة الخصم',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * حذف الخصم
     */
public function destroy($product_id)
{
    DB::beginTransaction();

    try {
        $product = Product::findOrFail($product_id);
        $user = Auth::user();

        $providerType = request()->is('api/service_provider*') ? 1 : 0;

        // Validate provider type and get provider info
        if ($providerType === 1) {
            if (!$user->Provider_service) {
                return response()->json([
                    'success' => false,
                    'message' => 'مزود الخدمة غير موجود'
                ], 404);
            }
            $providerId = $user->Provider_service->id;
            $providerTypeClass = 'App\\Models\\Provider_Service';

            // Verify ownership
            if ($product->providerable_id != $providerId || $product->providerable_type != $providerTypeClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بحذف هذا الخصم'
                ], 403);
            }
        } else {
            if (!$user->Provider_Product) {
                return response()->json([
                    'success' => false,
                    'message' => 'مزود المنتج غير موجود'
                ], 404);
            }
            $providerId = $user->Provider_Product->id;
            $providerTypeClass = 'App\\Models\\Provider_Product';

            // Verify ownership
            if ($product->providerable_id != $providerId || $product->providerable_type != $providerTypeClass) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بحذف هذا الخصم'
                ], 403);
            }
        }

        // Check if discount exists
        if (!$product->discount) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد خصم لهذا المنتج',
                'errors' => ['product_id' => ['لم يتم العثور على خصم لهذا المنتج']]
            ], 404);
        }

        // Delete the discount
        $product->discount()->delete();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الخصم بنجاح'
        ], 200);

    }  catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'خطأ في التحقق',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'فشل في تغيير حالة الخصم',
            'error' => $e->getMessage()
        ], 500);
        }
    }
}
