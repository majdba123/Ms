<?php

namespace App\Http\Requests\Subscribe;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\Category;
class StoreSubscribeRequest extends FormRequest
{
    public function authorize()
    {
        return true; // تأكد من السماح بتنفيذ هذا الطلب
    }

    public function rules()
    {
        return [
            'web_sub_id' => 'required|exists:web_subs,id',
        ];
    }


    public function messages()
    {
        return [
            'web_sub_id.required' => 'The web sub ID is required.',
            'web_sub_id.exists' => 'The selected web sub ID does not exist.',
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
