<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class AnglesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'topic' => ['required', 'string', 'max:2000'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
