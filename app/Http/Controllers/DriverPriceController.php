<?php

namespace App\Http\Controllers;

use App\Models\Driver_Price;
use Illuminate\Http\Request;

class DriverPriceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $prices = Driver_Price::all();
        return response()->json($prices);
    }



    public function store(Request $request)
    {
        $request->validate([
            'from_distance' => 'required|numeric|min:0',
            'to_distance' => 'required|numeric|gt:from_distance',
            'price' => 'required|numeric|min:0',
        ]);

        $price = Driver_Price::create($request->all());

        return response()->json($price, 201);
    }



    public function show($id)
    {
        $price = Driver_Price::find($id);

        if (!$price) {
            return response()->json(['message' => 'السعر غير موجود'], 404);
        }

        return response()->json($price);
    }

    /**
     * تحديث سعر موجود
     */
    public function update(Request $request, $id)
    {
        $price = Driver_Price::find($id);

        if (!$price) {
            return response()->json(['message' => 'السعر غير موجود'], 404);
        }

        $request->validate([
            'from_distance' => 'nullable|numeric|min:0',
            'to_distance' => 'nullable|numeric|gt:from_distance',
            'price' => 'nullable|numeric|min:0',
        ]);

        $price->update($request->all());

        return response()->json($price);
    }

    public function destroy($id)
    {
        $price = Driver_Price::find($id);

        if (!$price) {
            return response()->json(['message' => 'السعر غير موجود'], 404);
        }

        $price->delete();

        return response()->json(['message' => 'تم حذف السعر بنجاح']);
    }
}
