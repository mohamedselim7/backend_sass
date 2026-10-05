<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'headline' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'content' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'cta' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'design_idea' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:64'],
            'hashtags' => ['sometimes', 'array'],
            'hashtags.*' => ['string', 'max:64'],
            'media' => ['sometimes', 'array'],
            'media.*' => ['url', 'max:2048'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
