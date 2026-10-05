<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'generation_id' => $this->generation_id,
            'plan_index' => $this->plan_index,
            'name' => $this->name,
            'strategy' => $this->strategy,
            'goal' => $this->goal,
            'target_audience' => $this->target_audience,
            'pillars' => $this->pillars ?? [],
            'funnel' => $this->funnel ?? [],
            'formats' => $this->formats ?? [],
            'posts' => ContentPostResource::collection($this->whenLoaded('posts')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
