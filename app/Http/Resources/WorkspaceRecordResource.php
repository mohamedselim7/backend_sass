<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'brand_name' => $this->brand_name,
            'feature' => $this->feature,
            'title' => $this->title,
            'input' => $this->input ?? [],
            'result' => $this->result ?? [],
            'image_storage_path' => $this->image_storage_path,
            'provider' => $this->provider,
            'model' => $this->model,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
