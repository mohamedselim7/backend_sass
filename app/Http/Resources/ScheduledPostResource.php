<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduledPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'post_id' => $this->post_id,
            'platform' => $this->platform,
            'social_account_id' => $this->social_account_id,
            'caption' => $this->caption,
            'media' => $this->media ?? [],
            'hashtags' => $this->hashtags ?? [],
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'published_at' => $this->published_at?->toIso8601String(),
            'external_post_id' => $this->external_post_id,
            'last_error' => $this->last_error,
        ];
    }
}
