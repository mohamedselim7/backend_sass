<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivityLogRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'max:190'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'entity' => ['nullable', 'string', 'max:190'],
            'feature' => ['nullable', 'string', 'max:190'],
            'status' => ['nullable', 'string', 'max:32'],
            'cost' => ['nullable', 'numeric'],
            'details' => ['nullable', 'array'],
        ];
    }
}
