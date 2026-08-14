<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:quiz,tarea,foro,proyecto,evaluacion'],
            'base_points' => ['required', 'integer', 'min:1'],
            'max_attempts' => ['required', 'integer', 'min:1'],
            'due_at' => ['nullable', 'date'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
