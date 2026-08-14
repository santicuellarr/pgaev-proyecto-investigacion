<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GradeSubmissionRequest extends FormRequest
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
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
