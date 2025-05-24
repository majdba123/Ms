<?php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'qunatity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // Add time_of_service validation only for service providers
        if ($this->is('api/service_provider*')) {
            $rules['time_of_service'] = 'required|string|max:255';
        } else {
            $rules['time_of_service'] = 'nullable|string|max:255';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'The name field is required.',
            'description.required' => 'The description field is required.',
            'price.required' => 'The price field is required.',
            'qunatity.required' => 'The quantity field is required.',
            'time_of_service.required' => 'The time of service field is required for service providers.',
            'category_id.required' => 'The category ID field is required.',
            'images.required' => 'At least one image is required.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Each image must be of type jpeg, png, jpg, or gif.',
            'images.*.max' => 'Each image must not exceed 2048 kilobytes.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }
}
