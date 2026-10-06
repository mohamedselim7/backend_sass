<?php

namespace App\Http\Requests\Ai;

use App\Services\Ai\Prompts\DesignFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'format_id' => ['nullable', Rule::in(DesignFormat::ids())],
            'mode' => ['nullable', 'in:branded,free'],
            'platform' => ['nullable', 'string', 'max:64'],
            'content_text' => ['nullable', 'string', 'max:8000'],
            'style_instructions' => ['nullable', 'string', 'max:3000'],
            'negative_instructions' => ['nullable', 'string', 'max:3000'],
            // Mandatory design prompt from "Planning & Understanding" (branded designs only).
            'mandatory_prompt' => ['nullable', 'string', 'max:6000'],
        ];
    }
}
