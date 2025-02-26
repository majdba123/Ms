<?php

namespace App\Http\Requests\Subscribe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreWebSubRequest extends FormRequest
{
    public function authorize()
    {
        return true; // تأكد من السماح بتنفيذ هذا الطلب
    }

    public function rules()
    {
        return [
            'time' => 'required|integer|min:1|unique:web_subs,time',
            'price' => 'required|numeric|min:0',
        ];
    }

    public function messages()
    {
        return [
            'time.required' => 'The time is required.',
            'time.integer' => 'The time must be an integer.',
            'time.min' => 'The time must be at least 1.',
            'time.unique' => 'The time must be unique.',
            'price.required' => 'The price is required.',
            'price.numeric' => 'The price must be a number.',
            'price.min' => 'The price must be at least 0.',
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
