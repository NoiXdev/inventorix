<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'open_date' => ['required', 'date'],
            'closed_date' => ['nullable', 'date', 'after_or_equal:open_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
