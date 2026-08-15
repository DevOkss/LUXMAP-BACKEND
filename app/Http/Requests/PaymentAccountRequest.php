<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_provider' => ['nullable', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:255'],
            'qr_code_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}