<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $eventId = $this->route('event')?->id;

        return [
            'organization_id' => [$this->isMethod('POST') ? 'required' : 'nullable', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue' => ['nullable', 'string', 'max:255'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', 'after_or_equal:time_from'],
            'event_date' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:draft,published,completed'],
        ];
    }
}
