<?php
namespace App\Http\Requests\Discount;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DiscountUpdateRequest extends FormRequest
{
    public function rules()
    {
        return [
            'value' => 'sometimes|numeric|min:0|max:100',
            'from_time' => 'sometimes|date',
            'to_time' => 'sometimes|date|after:from_time',
            'status' => 'sometimes|in:active,inactive',
        ];
    }


    public function messages(): array
    {
        return [
            'value.sometimes' => 'The discount value field is required.',
            'value.numeric' => 'The discount value must be a number.',
            'value.min' => 'The discount value must be at least 0.',
            'value.max' => 'The discount value may not be greater than 100.',
            'from_time.sometimes' => 'The start time field is required.',
            'from_time.date' => 'The start time must be a valid date.',
            'to_time.sometimes' => 'The end time field is required.',
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
