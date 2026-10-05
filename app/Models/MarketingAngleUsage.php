<?php

namespace App\Models;

use App\Models\Concerns\HasUuidKey;
use Illuminate\Database\Eloquent\Model;

/** One real use of a marketing angle inside a brief/plan (snapshot kept as-is). */
class MarketingAngleUsage extends Model
{
    use HasUuidKey;

    protected $fillable = ['marketing_angle_id', 'user_id', 'generation_id', 'brief_excerpt', 'angle_snapshot'];

    protected function casts(): array
    {
        return ['angle_snapshot' => 'array'];
    }
}
