<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignVersion extends Model
{
    use HasFactory;
    use HasUuidKey;

    protected $fillable = [
        'design_id', 'version', 'image_url', 'image_storage_path', 'instructions', 'provider', 'model',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function design(): BelongsTo
    {
        return $this->belongsTo(Design::class);
    }
}
