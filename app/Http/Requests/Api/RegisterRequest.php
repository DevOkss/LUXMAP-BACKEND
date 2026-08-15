<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_number' => ['required', 'string', 'max:20', 'unique:users,student_number'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'institute' => ['required', 'string', 'max:100'],
            'program' => ['required', 'string', 'max:100'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
