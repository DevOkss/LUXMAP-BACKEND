<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'exists:organizations,id'],
            'academic_term_id' => ['nullable', 'exists:academic_terms,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'term' => ['nullable', 'string', 'max:255'],
            'required_years' => ['nullable', 'array'],
            'required_years.*' => ['string', 'in:1,2,3,4,all'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:draft,posted'],
        ];
    }
}
