<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'locale' => $this->locale,
            'status' => $this->status,
            'active_model' => $this->active_model,
            'email_verified' => $this->hasVerifiedEmail(),
            'must_set_password' => (bool) $this->must_set_password,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'credits' => $this->whenLoaded('wallet', fn () => (int) $this->wallet->balance),
            'subscription' => $this->whenLoaded('activeSubscription', fn () => $this->activeSubscription ? [
                'id' => $this->activeSubscription->id,
                'status' => $this->activeSubscription->status,
                'plan' => $this->activeSubscription->plan?->code,
                'ends_at' => $this->activeSubscription->ends_at?->toIso8601String(),
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
