<?php

namespace App\Http\Requests\Content;

use App\Enums\PostStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionPostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PostStatus::class)],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:status,rejected'],
        ];
    }
}
