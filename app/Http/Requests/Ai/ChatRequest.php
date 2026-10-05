<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'thread_id' => ['nullable', 'uuid', 'exists:chat_threads,id'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'message' => ['required', 'string', 'max:20000'],
            'mode' => ['nullable', 'in:free,smart'],
            'page_context' => ['nullable', 'string', 'max:64'],
        ];
    }
}
