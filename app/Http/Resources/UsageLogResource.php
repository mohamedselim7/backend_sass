<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand_name,
            'kind' => $this->kind,
            'feature' => $this->feature,
            'action' => $this->action,
            'provider' => $this->provider,
            'model' => $this->model,
            'tokens' => $this->tokens,
            'prompt_tokens' => $this->prompt_tokens,
            'completion_tokens' => $this->completion_tokens,
            'images' => $this->images,
            'cost' => $this->cost !== null ? (float) $this->cost : null,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
