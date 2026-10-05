<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class DesignAiRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'post_id' => ['nullable', 'uuid', 'exists:content_posts,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'prompt' => ['required', 'string', 'max:5000'],
            'headline' => ['nullable', 'string', 'max:1000'],
            'format' => ['nullable', 'string', 'max:32'],
        ];
    }
}
