<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentPostFactory extends Factory
{
    protected $model = \App\Models\ContentPost::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'brand_id' => Brand::factory(),
            'post_number' => 1,
            'headline' => $this->faker->sentence(),
            'content' => $this->faker->paragraph(),
            'platform' => 'instagram',
            'status' => PostStatus::Generated->value,
            'source' => 'ai',
        ];
    }
}
