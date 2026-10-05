<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'design_id' => $this->design_id,
            'version' => (int) $this->version,
            'image_url' => $this->image_url,
            'instructions' => $this->instructions,
            'provider' => $this->provider,
            'model' => $this->model,
            'cost' => $this->cost,
            'remaining_credits' => $this->remaining_credits,
            'applied_mandatory' => (bool) $this->applied_mandatory,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}