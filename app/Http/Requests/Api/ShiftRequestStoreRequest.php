<?php

namespace App\Http\Requests\Api;

use App\Models\Program;
use Illuminate\Foundation\Http\FormRequest;

class ShiftRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $institute = $this->input('requested_institute');

        return [
            'requested_institute' => [
                'required', 'string',
                \Illuminate\Validation\Rule::exists('institutes', 'code')->where('is_active', true),
            ],
            'requested_program' => [
                'required', 'string',
                function ($attribute, $value, $fail) use ($institute) {
                    $belongsToInstitute = Program::query()
                        ->where('code', $value)
                        ->where('is_active', true)
                        ->whereHas('institute', fn ($q) => $q->where('code', $institute))
                        ->exists();

                    if (!$belongsToInstitute) {
                        $fail('The requested program does not belong to the requested institute.');
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}