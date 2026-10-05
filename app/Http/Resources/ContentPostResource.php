<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContentPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'brand_id' => $this->brand_id,
            'post_number' => $this->post_number,
            'content_type' => $this->content_type,
            'funnel_stage' => $this->funnel_stage,
            'headline' => $this->headline,
            'content' => $this->content,
            'cta' => $this->cta,
            'design_idea' => $this->design_idea,
            'platform' => $this->platform,
            'status' => $this->status->value,
            'reject_reason' => $this->reject_reason,
            'source' => $this->source,
            'hashtags' => $this->hashtags ?? [],
            'media' => $this->media ?? [],
            'suggested_platforms' => $this->suggested_platforms ?? [],
            'notes' => $this->notes,
            'language_id' => $this->language_id,
            'dialect_id' => $this->dialect_id,
            'tone_id' => $this->tone_id,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
