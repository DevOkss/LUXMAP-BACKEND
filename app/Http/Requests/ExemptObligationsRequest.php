<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExemptObligationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'fee_ids' => ['sometimes', 'array'],
            'fee_ids.*' => ['integer', 'exists:fees,id'],
            'event_ids' => ['sometimes', 'array'],
            'event_ids.*' => ['integer', 'exists:events,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if (empty($this->input('fee_ids') ?? []) && empty($this->input('event_ids') ?? [])) {
                $validator->errors()->add('items', 'Select at least one outstanding obligation.');
            }
        });
    }

    public function messages(): array
    {
        return ['reason.required' => 'A reason is required to exempt an obligation.'];
    }
}