<?php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class FilterProduct extends FormRequest
{
    public function authorize()
    {
        return true; // تأكد من السماح بتنفيذ هذا الطلب
    }

    public function rules()
    {
        return [
            'category_id' => 'nullable|integer|exists:categories,id',
            'name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            'category_id.integer' => 'The category ID must be an integer.',
            'category_id.exists' => 'The specified category ID does not exist in the database.',
            'name.string' => 'The product name must be a string.',
            'name.max' => 'The product name must not exceed 255 characters.',
            'price.numeric' => 'The product price must be a number.',
            'price.min' => 'The product price must be at least 0.',
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
