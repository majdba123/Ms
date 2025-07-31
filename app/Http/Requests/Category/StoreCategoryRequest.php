<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return True;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type' => 'required|in:0,1,2', // تغيير من boolean إلى in:0,1,2
            'price' => 'required|numeric',
            'imag' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'type.required' => 'The type field is required.',
            'type.in' => 'The type must be 0, 1, or 2.', // تحديث رسالة الخطأ
            'price.required' => 'The price field is required.',
            'price.numeric' => 'The price must be a number.',
            'imag.image' => 'The file must be an image.',
            'imag.mimes' => 'The image must be a file of type: jpeg, png, jpg, gif.',
            'imag.max' => 'The image may not be greater than 2MB in size.'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        // Customize the response for validation errors
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }


    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('type') == 0 && $this->input('price') > 100) {
                $validator->errors()->add('price', 'The price for a product type category may not be greater than 100.');
            }
            if ($this->input('type') == 2 && $this->input('price') > 100) {
                $validator->errors()->add('price', 'The price for a food type category may not be greater than 100.');
            }
        });
    }
}
