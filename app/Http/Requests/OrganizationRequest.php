<?php

namespace App\Http\Requests;

use App\Enums\OrganizationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $organizationId = $this->route('organization')?->id;

        return [
            'parent_id' => ['nullable', 'exists:organizations,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:organizations,code,' . $organizationId],
            'type' => ['required', new Enum(OrganizationType::class)],
            'description' => ['nullable', 'string'],
            'config' => ['nullable', 'json'],
            'is_active' => ['boolean'],
        ];
    }
}
