<?php
namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\FoodType_ProductProvider;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

   public function rules()
{
    $rules = [
        'name' => 'sometimes|string|max:255',
        'description' => 'sometimes|string',
        'price' => 'sometimes|numeric|min:0',
        'category_id' => [
            'sometimes',
            'exists:categories,id',
            function ($attribute, $value, $fail) {
                if (auth()->user()->type == 'food_provider' && $this->has('category_id')) {
                    $categoryType = \App\Models\Category::find($value)->type;
                    if ($categoryType != 2) {
                        $fail('For food providers, the category must be of type 2.');
                    }
                }
            }
        ],
        'images' => 'sometimes|array',
        'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
    ];

    // Add validation rules based on the route
    if ($this->is('api/service_provider*')) {
        $rules['time_of_service'] = 'sometimes|required|string|max:255';
        $rules['quantity'] = 'nullable|integer|min:0';
    } else {
        $rules['time_of_service'] = 'nullable|string|max:255';
        $rules['quantity'] = 'sometimes|required|integer|min:0';

        // إذا كان نوع المستخدم food_provider
        if (auth()->user()->type == 'food_provider') {
            $rules['food_type_id'] = [
                'sometimes',
                'required',
                'integer',
                'exists:food_types,id',
                function ($attribute, $value, $fail) {
                    $providerProductId = auth()->user()->provider_product->id ?? null;
                    if (!FoodType_ProductProvider::where('food_type_id', $value)
                        ->where('provider__product_id', $providerProductId)
                        ->exists()) {
                        $fail('This food type is not associated with your provider account.');
                    }
                }
            ];
        }
    }

    return $rules;
}
    public function messages()
    {
        return [
            'name.sometimes' => 'The name field is optional, but must be a valid string if provided.',
            'description.sometimes' => 'The description field is optional, but must be a valid string if provided.',
            'price.sometimes' => 'The price field is optional, but must be a valid number if provided.',
            'category_id.sometimes' => 'The category ID field is optional, but must exist in categories if provided.',
            'images.sometimes' => 'The images field is optional, but must be an array if provided.',
            'quantity.required' => 'The quantity field is required when updating a product.',
            'quantity.integer' => 'The quantity must be an integer.',
            'quantity.min' => 'The quantity must be at least 0.',
            'time_of_service.required' => 'The time of service field is required when updating a service.',
            'time_of_service.string' => 'The time of service must be a string.',
            'time_of_service.max' => 'The time of service may not be greater than 255 characters.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Each image must be of type jpeg, png, jpg, or gif.',
            'images.*.max' => 'Each image must not exceed 2048 kilobytes.',
            'food_type_id.required' => 'The food type ID is required for food providers when provided.',
            'food_type_id.integer' => 'The food type ID must be an integer.',
            'food_type_id.exists' => 'The selected food type does not exist.',
        ];
    }
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }
}
