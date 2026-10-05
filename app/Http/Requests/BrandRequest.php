<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BrandRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:160'],
            'industry' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'target_audience' => ['nullable', 'string', 'max:2000'],
            'target_market' => ['nullable', 'string', 'max:2000'],
            'tone_of_voice' => ['nullable', 'string', 'max:500'],
            'content_language' => ['nullable', 'string', 'max:32'],
            'dialect' => ['nullable', 'string', 'max:64'],
            'platforms' => ['nullable', 'array'],
            'platforms.*' => ['string', 'max:64'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
            'colors' => ['nullable', 'array'],
            'colors.*' => ['string', 'max:32'],
            'fonts' => ['nullable', 'string', 'max:255'],
            'guidelines_url' => ['nullable', 'url', 'max:2048'],
            'reference_images' => ['nullable', 'array'],
            'reference_images.*' => ['url', 'max:2048'],
            'website' => ['nullable', 'url', 'max:2048'],
            'social' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
