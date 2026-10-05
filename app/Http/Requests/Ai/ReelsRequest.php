<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ReelsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:plan,single'],
            'prompt' => ['nullable', 'string', 'max:5000'],
            'brief' => ['nullable', 'string', 'max:20000'],
            'count' => ['required', 'integer', 'min:1', 'max:20'],
            'platform' => ['nullable', 'string', 'max:64'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:600'],
            'language' => ['nullable', 'string', 'max:32'],
            'dialect' => ['nullable', 'string', 'max:32'],
            'tone' => ['nullable', 'string', 'max:64'],
            'audience' => ['nullable', 'string', 'max:1000'],
            'goal' => ['nullable', 'string', 'max:1000'],
            'captionPrompt' => ['nullable', 'string', 'max:2000'],
            'avoidTitles' => ['nullable', 'array'],
            'avoidTitles.*' => ['string', 'max:255'],
            'brandId' => ['nullable', 'uuid', 'exists:brands,id'],
            'brandName' => ['nullable', 'string', 'max:160'],
        ];
    }
}
