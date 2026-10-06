<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = (bool) $request->user()?->hasRole('admin');

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'interval' => $this->interval,
            'monthly_credits' => (int) $this->monthly_credits,
            'features' => $this->features ?? [],
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            // Internal routing/model fields are only exposed to admins.
            'ai_model' => $this->when($isAdmin, $this->ai_model),
            'ai_queue' => $this->when($isAdmin, $this->ai_queue),
            'queue_priority' => $this->when($isAdmin, $this->queue_priority),
        ];
    }
}
