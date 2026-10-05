<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand_name,
            'actor' => $this->actor,
            'action' => $this->action,
            'entity' => $this->entity,
            'feature' => $this->feature,
            'status' => $this->status,
            'cost' => $this->cost !== null ? (float) $this->cost : null,
            'details' => $this->details,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
