<?php
namespace App\Http\Requests\Discount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DiscountStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'value' => 'required|numeric|min:0|max:100',
            'from_time' => 'required|date',
            'to_time' => 'required|date|after:from_time',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'value.required' => 'The discount value field is required.',
            'value.numeric' => 'The discount value must be a number.',
            'value.min' => 'The discount value must be at least 0.',
            'value.max' => 'The discount value may not be greater than 100.',
            'from_time.required' => 'The start time field is required.',
            'from_time.*' => 'The start time must be current time or in the future.',
            'from_time.date' => 'The start time must be a valid date.',
            'to_time.required' => 'The end time field is required.',
            'to_time.date' => 'The end time must be a valid date.',
            'to_time.after' => 'The end time must be after the start time.',

        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'errors' => $validator->errors(),
        ], 422));
    }


}
