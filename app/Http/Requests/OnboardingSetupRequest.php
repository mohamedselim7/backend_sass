<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OnboardingSetupRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:190'],
            'brand' => ['nullable', 'array'],
            'brand.name' => ['required_with:brand', 'string', 'max:160'],
            'brand.industry' => ['nullable', 'string', 'max:160'],
            'brand.description' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'array'],
            'goals.*.title' => ['required', 'string', 'max:255'],
            'goals.*.metric' => ['nullable', 'string', 'max:64'],
            'goals.*.target_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
