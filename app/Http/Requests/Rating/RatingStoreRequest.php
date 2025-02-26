<?php
namespace App\Http\Requests\Rating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RatingStoreRequest extends FormRequest
{
    public function authorize()
    {
        return true; // تأكد من السماح بتنفيذ هذا الطلب
    }

    public function rules()
    {
        return [
            'num' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'num.required' => 'The rating field is required.',
            'num.integer' => 'The rating must be an integer.',
            'num.min' => 'The rating must be at least 1.',
            'num.max' => 'The rating must not be greater than 5.',
            'comment.string' => 'The comment must be a string.',
            'comment.max' => 'The comment must not exceed 255 characters.',
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
