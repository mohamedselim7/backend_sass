<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkspaceRecordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'feature' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:255'],
            'brand_id' => ['nullable', 'uuid', 'exists:brands,id'],
            'brand_name' => ['nullable', 'string', 'max:160'],
            'input' => ['nullable', 'array'],
            'result' => ['nullable', 'array'],
            'image_storage_path' => ['nullable', 'string', 'max:1024'],
            'provider' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:120'],
        ];
    }
}
