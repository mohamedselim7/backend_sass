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
            'model' => ['nullable', 'string', 'max:120'],
            'mode' => ['nullable', 'in:plan,single'],
            'avoid_headlines' => ['nullable', 'array', 'max:80'],
            'avoid_headlines.*' => ['string', 'max:300'],
            'language_id' => ['nullable', 'string', 'max:64'],
            'dialect_id' => ['nullable', 'string', 'max:64'],
            'tone_id' => ['nullable', 'string', 'max:64'],
            'content_format' => ['nullable', 'string', 'max:64'],
            // Mandatory design prompt from "Planning & Understanding" — bound into every design_idea.
            'visual_identity' => ['nullable', 'string', 'max:6000'],
            'target_audience' => ['nullable', 'string', 'max:2000'],
            'target_market' => ['nullable', 'string', 'max:500'],
        ];
    }
}
