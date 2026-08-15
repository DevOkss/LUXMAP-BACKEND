<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'fee_ids' => ['sometimes', 'array'],
            'fee_ids.*' => ['integer', 'exists:fees,id'],
            'event_ids' => ['sometimes', 'array'],
            'event_ids.*' => ['integer', 'exists:events,id'],
            'reference_number' => ['required', 'string', 'max:255'],
            'payment_channel' => ['nullable', 'string', 'in:gcash,maya,bank_transfer,other'],
            'receipt_image' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if (empty($this->input('fee_ids') ?? []) && empty($this->input('event_ids') ?? [])) {
                $validator->errors()->add('items', 'Select at least one outstanding obligation to pay.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'receipt_image.max' => 'The receipt image must not be larger than 5MB.',
            'reference_number.required' => 'The payment reference number is required.',
        ];
    }
}