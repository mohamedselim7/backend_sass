<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'path' => $this->path,
            'mime' => $this->mime,
            'size' => $this->size,
            'folder' => $this->folder,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
