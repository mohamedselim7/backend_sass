<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'section' => $this->section,
            'prompt' => $this->prompt ?? $this->body,
            'result' => $this->result,
            'notes' => $this->notes,
            'is_favorite' => (bool) $this->is_favorite,
            'is_shared' => (bool) $this->is_shared,
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand_name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
