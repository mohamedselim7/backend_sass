<?php

namespace App\Http\Requests\Ai;

use App\Services\Ai\Prompts\DesignFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateImageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:5000'],
            'size' => ['nullable', 'string', 'max:32'],
            'format_id' => ['nullable', Rule::in(DesignFormat::ids())],
            'folder' => ['nullable', 'string', 'max:120'],
        ];
    }
}
