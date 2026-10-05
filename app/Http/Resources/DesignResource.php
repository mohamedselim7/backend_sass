<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'brand_id' => $this->brand_id,
            'headline' => $this->headline,
            'description' => $this->description,
            'design_idea' => $this->design_idea,
            'format' => $this->format,
            'status' => $this->status,
            'versions' => DesignVersionResource::collection($this->whenLoaded('versions')),
            'latest_version' => new DesignVersionResource($this->whenLoaded('latestVersion')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
