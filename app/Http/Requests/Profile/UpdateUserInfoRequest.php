<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateUserInfoRequest extends FormRequest
{
    public function authorize()
    {
        return true; // تأكد من السماح بتنفيذ هذا الطلب
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'current_password' => 'required_with:password|string',
            'national_id' => 'sometimes|string|size:14|unique:users,national_id,' . auth()->id(),

            'password' => 'sometimes|required|string|min:8|confirmed',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'The name field is required when provided.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name must not exceed 255 characters.',
            'current_password.required_with' => 'The current password is required when updating the password.',
            'password.required' => 'The password field is required when provided.',
            'password.string' => 'The password must be a string.',
            'password.min' => 'The password must be at least 8 characters long.',
            'password.confirmed' => 'The password confirmation does not match.',
                        'national_id.size' => 'الرقم القومي يجب أن يتكون من 14 رقمًا',
            'national_id.unique' => 'الرقم القومي مسجل بالفعل',
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
