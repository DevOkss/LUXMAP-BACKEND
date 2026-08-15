<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qr_configuration_id' => ['required', 'integer', 'exists:qr_configurations,id'],
            'scanned_at' => ['nullable', 'date'],
        ];
    }
}
