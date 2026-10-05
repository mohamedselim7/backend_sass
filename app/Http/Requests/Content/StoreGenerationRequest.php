<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class StoreGenerationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'business_brief' => ['nullable', 'string', 'max:20000'],
            'monthly_brief' => ['nullable', 'string', 'max:20000'],
            'options' => ['nullable', 'array'],
            'provider' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:120'],
            'write_mode' => ['nullable', 'string', 'max:32'],

            'plans' => ['required', 'array', 'min:1', 'max:10'],
            'plans.*.name' => ['nullable', 'string', 'max:255'],
            'plans.*.strategy' => ['nullable', 'string', 'max:10000'],
            'plans.*.goal' => ['nullable', 'string', 'max:2000'],
            'plans.*.target_audience' => ['nullable', 'string', 'max:2000'],
            'plans.*.pillars' => ['nullable', 'array'],
            'plans.*.funnel' => ['nullable', 'array'],
            'plans.*.formats' => ['nullable', 'array'],
            'plans.*.posts' => ['nullable', 'array', 'max:120'],
            'plans.*.posts.*.post_number' => ['nullable', 'integer', 'min:1'],
            'plans.*.posts.*.content_type' => ['nullable', 'string', 'max:64'],
            'plans.*.posts.*.funnel_stage' => ['nullable', 'string', 'max:64'],
            'plans.*.posts.*.headline' => ['nullable', 'string', 'max:1000'],
            'plans.*.posts.*.content' => ['nullable', 'string', 'max:20000'],
            'plans.*.posts.*.cta' => ['nullable', 'string', 'max:1000'],
            'plans.*.posts.*.design_idea' => ['nullable', 'string', 'max:5000'],
            'plans.*.posts.*.platform' => ['nullable', 'string', 'max:64'],
            'plans.*.posts.*.hashtags' => ['nullable', 'array'],
            'plans.*.posts.*.media' => ['nullable', 'array'],
        ];
    }
}
