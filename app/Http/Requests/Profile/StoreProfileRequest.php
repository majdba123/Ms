<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;


class StoreProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true; // أو استخدم منطق التفويض المناسب
    }

    public function rules()
    {
        return [
            'lang' => 'required',
            'lat' => 'required',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'address' => 'required|string',
        ];
    }

    public function messages()
    {
        return [
            'lang.required' => 'The language field is required.',
            'lat.required' => 'The latitude field is required.',
            'image.required' => 'The image field is required.',
            'image.image' => 'The uploaded file must be an image.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, svg.',
            'image.max' => 'The image must not be greater than 2048 kilobytes.',
            'address.required' => 'The address field is required.',
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
