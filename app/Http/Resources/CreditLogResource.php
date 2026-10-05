<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (int) $this->amount,
            'balance_after' => (int) $this->balance_after,
            'reason' => $this->reason->value,
            'feature' => $this->feature,
            'ref_id' => $this->ref_id,
            'details' => $this->details ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
