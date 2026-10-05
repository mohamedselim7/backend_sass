<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class GenerateContentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'brief' => ['required', 'string', 'max:20000'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['string', 'max:64'],
            'plan_count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'posts_per_plan' => ['nullable', 'integer', 'min:1', 'max:30'],
            'async' => ['nullable', 'boolean'],
        ];
    }
}
