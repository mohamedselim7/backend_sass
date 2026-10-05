<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class GenerateImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:5000'],
            'size' => ['nullable', 'string', 'max:32'],
            'folder' => ['nullable', 'string', 'max:120'],
        ];
    }
}
