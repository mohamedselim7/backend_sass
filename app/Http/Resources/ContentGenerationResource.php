<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentGenerationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand_name,
            'business_brief' => $this->business_brief,
            'monthly_brief' => $this->monthly_brief,
            'options' => $this->options ?? [],
            'status' => $this->status,
            'provider' => $this->provider,
            'model' => $this->model,
            'write_mode' => $this->write_mode,
            'prompt_version' => $this->prompt_version,
            'plans' => ContentPlanResource::collection($this->whenLoaded('plans')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
