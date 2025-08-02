<?php

namespace App\Http\Controllers;

use App\Models\FoodType;
use Illuminate\Http\Request;

class FoodTypeController extends Controller
{
  public function index()
    {
        $foodTypes = FoodType::with('FoodType_ProductProvider')->get();
        return response()->json([
            'success' => true,
            'data' => $foodTypes
        ], 200);
    }

    /**
     * حفظ نوع الطعام الجديد في قاعدة البيانات (POST)
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $foodType = FoodType::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء نوع الطعام بنجاح',
            'data' => $foodType
        ], 201);
    }

    /**
     * عرض نوع طعام معين (GET)
     */
    public function show($id)
    {
        $foodType = FoodType::with('FoodType_ProductProvider')->find($id);

        if (!$foodType) {
            return response()->json([
                'success' => false,
                'message' => 'نوع الطعام غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $foodType
        ], 200);
    }

    /**
     * تحديث نوع طعام معين (PUT/PATCH)
     */
    public function update(Request $request, $id)
    {
        $foodType = FoodType::find($id);

        if (!$foodType) {
            return response()->json([
                'success' => false,
                'message' => 'نوع الطعام غير موجود'
            ], 404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $foodType->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث نوع الطعام بنجاح',
            'data' => $foodType
        ], 200);
    }

    /**
     * حذف نوع طعام معين (DELETE)
     */
    public function destroy($id)
    {
        $foodType = FoodType::find($id);

        if (!$foodType) {
            return response()->json([
                'success' => false,
                'message' => 'نوع الطعام غير موجود'
            ], 404);
        }

        $foodType->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف نوع الطعام بنجاح'
        ], 200);
    }
}
