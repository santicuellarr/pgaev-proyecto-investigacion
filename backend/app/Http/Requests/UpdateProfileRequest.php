<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
