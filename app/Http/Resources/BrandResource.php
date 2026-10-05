<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'description' => $this->description,
            'target_audience' => $this->target_audience,
            'target_market' => $this->target_market,
            'tone_of_voice' => $this->tone_of_voice,
            'content_language' => $this->content_language,
            'dialect' => $this->dialect,
            'platforms' => $this->platforms ?? [],
            'logo_url' => $this->logo_url,
            'colors' => $this->colors ?? [],
            'fonts' => $this->fonts,
            'guidelines_url' => $this->guidelines_url,
            'reference_images' => $this->reference_images ?? [],
            'website' => $this->website,
            'social' => $this->social ?? [],
            'notes' => $this->notes,
            'is_demo' => (bool) $this->is_demo,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
