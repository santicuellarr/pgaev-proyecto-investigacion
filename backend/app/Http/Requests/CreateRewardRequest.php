<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRewardRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'cost_points' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'needs_approval' => ['sometimes', 'boolean'],
            'available_from' => ['nullable', 'date'],
            'available_to' => ['nullable', 'date', 'after_or_equal:available_from'],
        ];
    }
}
