<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'title' => $this->title,
            'description' => $this->description,
            'metric' => $this->metric,
            'target_value' => $this->target_value,
            'current_value' => $this->current_value,
            'period' => $this->period,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'status' => $this->status,
            'meta' => $this->meta ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
