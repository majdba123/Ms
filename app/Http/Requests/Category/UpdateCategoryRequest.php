<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCategoryRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|boolean',
            'price' => 'sometimes|numeric',
            'imag' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'

        ];
    }

    public function messages(): array
    {
        return [
            'name.sometimes' => 'The name field is sometimes required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'type.sometimes' => 'The type field is sometimes required.',
            'type.boolean' => 'The type must be 0 or 1.',
            'price.sometimes' => 'The price field is sometimes required.',
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
        });
    }
}
