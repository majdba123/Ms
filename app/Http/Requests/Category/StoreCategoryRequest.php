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
            'type' => 'required|boolean',
            'price' => 'required|numeric',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a string.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'type.required' => 'The type field is required.',
            'type.boolean' => 'The type must be 0 or 1.',
            'price.required' => 'The price field is required.',
            'price.numeric' => 'The price must be a number.',
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
