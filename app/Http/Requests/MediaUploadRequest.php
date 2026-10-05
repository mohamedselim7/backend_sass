<?php

namespace App\Http\Requests;

use App\Services\MediaService;
use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(MediaService::MAX_BYTES / 1024),
                'mimetypes:'.implode(',', MediaService::ALLOWED_MIMES),
            ],
            'folder' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9_\-\/]+$/'],
        ];
    }
}
