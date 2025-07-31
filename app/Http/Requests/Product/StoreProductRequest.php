<?php
namespace App\Http\Requests\Product;

use App\Models\FoodType_ProductProvider;
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
        'category_id' => [
            'required',
            'exists:categories,id',
            function ($attribute, $value, $fail) {
                if (auth()->user()->type == 'food_provider') {
                    $categoryType = \App\Models\Category::find($value)->type;
                    if ($categoryType != 2) {
                        $fail('For food providers, the category must be of type 2.');
                    }
                }
            }
        ],
        'images' => 'required|array',
        'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
    ];

    if ($this->is('api/admin*')) {
        $rules['provider_id'] = [
            'required',
            'integer',
            function ($attribute, $value, $fail) {
                if ($this->provider_type == 1 && !\App\Models\Provider_Service::where('id', $value)->exists()) {
                    $fail('The selected provider does not exist or is not a service provider.');
                }
                if ($this->provider_type == 0 && !\App\Models\Provider_Product::where('id', $value)->exists()) {
                    $fail('The selected provider does not exist or is not a product provider.');
                }
            }
        ];
        $rules['provider_type'] = 'required|in:0,1';
    }

    if ($this->is('api/product_provider*') || (isset($this->provider_type) && $this->provider_type == 0)) {
        $rules['time_of_service'] = 'nullable|string|max:255';
        $rules['quantity'] = 'required|integer|min:0';

        if (auth()->user()->type == 'food_provider') {
            $rules['food_type_id'] = [
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
    } else {
        $rules['time_of_service'] = 'required|string|max:255';
        $rules['quantity'] = 'nullable|integer|min:0';
    }

    return $rules;
}

    public function messages()
    {
        return [
            // ... الرسائل الحالية ...
            'food_type_id.required' => 'The food type ID is required for food providers.',
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
