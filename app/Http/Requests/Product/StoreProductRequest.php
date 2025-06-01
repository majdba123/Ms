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
            'category_id' => 'required|exists:categories,id',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // إذا كان المستخدم أدمن وأرسل provider_id و provider_type
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

        // Add validation rules based on the route or provider_type
        if ($this->is('api/service_provider*') || (isset($this->provider_type) && $this->provider_type == 1)) {
            $rules['time_of_service'] = 'required|string|max:255';
            $rules['quantity'] = 'nullable|integer|min:0';
        } else {
            $rules['time_of_service'] = 'nullable|string|max:255';
            $rules['quantity'] = 'required|integer|min:0';
        }

        return $rules;
    }

    public function messages()
    {
        return [
            'name.required' => 'The name field is required.',
            'description.required' => 'The description field is required.',
            'price.required' => 'The price field is required.',
            'quantity.required' => 'The quantity field is required for product providers.',
            'time_of_service.required' => 'The time of service field is required for service providers.',
            'category_id.required' => 'The category ID field is required.',
            'images.required' => 'At least one image is required.',
            'images.*.image' => 'Each file must be an image.',
            'images.*.mimes' => 'Each image must be of type jpeg, png, jpg, or gif.',
            'images.*.max' => 'Each image must not exceed 2048 kilobytes.',
            'provider_id.required' => 'Provider ID is required for admin users.',
            'provider_type.required' => 'Provider type is required for admin users.',
            'provider_type.in' => 'Provider type must be 0 (product) or 1 (service).',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }
}
