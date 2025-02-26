<?php

namespace App\Http\Requests\AnswerRating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAnswerRatingRequest extends FormRequest
{
    public function authorize()
    {
        return true; // تأكد من السماح بتنفيذ هذا الطلب
    }

    public function rules()
    {
        return [
            'comment' => 'sometimes|required|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'comment.required' => 'The comment field is required.',
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
