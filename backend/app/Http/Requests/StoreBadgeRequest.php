<?php

namespace App\Http\Requests;

use App\Models\Badge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBadgeRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:32'],
            'rule_type' => ['required', Rule::in([
                Badge::RULE_FIRST_SUBMISSION,
                Badge::RULE_SUBMISSIONS_COUNT,
                Badge::RULE_POINTS_TOTAL,
                Badge::RULE_LEVEL_REACHED,
                Badge::RULE_MANUAL,
            ])],
            'rule_value' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
