<?php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true; // تأكد من السماح بتنفيذ هذا الطلب
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'images' => 'required|array', // التأكد من وجود الصور كمصفوفة
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // التحقق من كل صورة في المصفوفة
        ];
    }

    public function messages()
    {
        return [
        'name.required' => 'The name field is required.',
        'description.required' => 'The description field is required.',
        'price.required' => 'The price field is required.',
        'category_id.required' => 'The category ID field is required.',
        'images.required' => 'At least one image is required.',
        'images.*.image' => 'Each file must be an image.',
        'images.*.mimes' => 'Each image must be of type jpeg, png, jpg, or gif.',
        'images.*.max' => 'Each image must not exceed 2048 kilobytes.',
    ];
    }

    protected function failedValidation(Validator $validator)
    {
        // تخصيص رسالة الخطأ
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }
}
