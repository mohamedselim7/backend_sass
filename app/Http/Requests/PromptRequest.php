<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromptRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'section' => ['nullable', 'string', 'max:64'],
            'prompt' => [$required, 'string'],
            'result' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'is_favorite' => ['nullable', 'boolean'],
            'is_shared' => ['nullable', 'boolean'],
        ];
    }
}
