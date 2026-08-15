<?php

namespace App\Http\Requests\Api;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $institute = $this->input('institute');

        return [
            'email' => [
                'nullable', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user()->id),
            ],
            'institute' => [
                'required', 'string',
                Rule::exists('institutes', 'code')->where('is_active', true),
            ],
            'program' => [
                'required', 'string',
                function ($attribute, $value, $fail) use ($institute) {
                    $belongsToInstitute = Program::query()
                        ->where('code', $value)
                        ->where('is_active', true)
                        ->whereHas('institute', fn ($query) => $query->where('code', $institute))
                        ->exists();

                    if (! $belongsToInstitute) {
                        $fail('The selected program does not belong to the selected institute.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'institute.exists' => 'The selected institute is invalid.',
        ];
    }
}
