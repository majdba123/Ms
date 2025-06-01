<?php

namespace App\Http\Requests\registartion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterUser extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'type' => 'required|integer|in:0,1,2,3',
        ];

        // إضافة القواعد للرقم الوطني والصورة فقط إذا كان النوع 1 أو 2 أو 3
        if (in_array($this->type, [1, 2, 3])) {
            $rules['national_id'] = 'required|string|size:14|unique:users';
            $rules['image'] = 'required|image|mimes:jpeg,png,jpg,gif|max:2048';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'Email has already been taken.',
            'phone.unique' => 'Phone has already been taken.',
            'national_id.required' => 'National ID is required for this user type.',
            'national_id.size' => 'National ID must be exactly 14 digits.',
            'national_id.unique' => 'National ID has already been taken.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'type.required' => 'User type is required.',
            'type.in' => 'User type must be 0, 1, 2, or 3.',
            'image.required' => 'Image is required for this user type.',
            'image.image' => 'The file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif.',
            'image.max' => 'The image must not exceed 2048 kilobytes.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if (!$this->has('email') && !$this->has('phone')) {
                $validator->errors()->add('email_or_phone', 'You must provide either an email address or a phone number.');
            } elseif ($this->has('email') && $this->has('phone')) {
                $validator->errors()->add('email_or_phone', 'You must provide either an email address or a phone number, not both.');
            }
        });
    }
}
