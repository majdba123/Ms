<?php

namespace App\Http\Requests\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProviderProductCategory extends FormRequest
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
    public function messages()
    {
        return [
            'category_ids.required' => 'The category IDs are required.',
            'category_ids.array' => 'The category IDs must be an array.',
            'category_ids.*.exists' => 'One or more selected categories do not exist.'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        // تخصيص رسالة الخطأ
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $categoryIds = $this->input('category_ids', []);
            $invalidCategories = Category::whereIn('id', $categoryIds)
                ->where('type', '!=', 0)
                ->pluck('id')
                ->toArray();

            if (!empty($invalidCategories)) {
                $validator->errors()->add('category_ids', 'All selected categories must have type 0.');
            }
        });
    }
}
