<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            // Internal model is only exposed to admins.
            'ai_model' => $this->when($request->user()?->hasRole('admin'), $this->ai_model),
        ];
    }
}
