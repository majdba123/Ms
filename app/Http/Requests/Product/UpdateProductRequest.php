<?php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true; // تأكد من السماح بتنفيذ هذا الطلب
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'images' => 'sometimes|array', // التأكد من وجود الصور كمصفوفة إذا كانت موجودة
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // التحقق من كل صورة في المصفوفة إذا كانت موجودة
        ];
    }

    public function messages()
    {
        return [
            'name.sometimes' => 'The name field is optional, but must be a valid string if provided.',
            'description.sometimes' => 'The description field is optional, but must be a valid string if provided.',
            'price.sometimes' => 'The price field is optional, but must be a valid number if provided.',
            'category_id.sometimes' => 'The category ID field is optional, but must exist in categories if provided.',
            'images.sometimes' => 'The images field is optional, but must be an array if provided.',
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
