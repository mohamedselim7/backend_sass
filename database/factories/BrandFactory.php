<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BrandFactory extends Factory
{
    protected $model = \App\Models\Brand::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'name' => $this->faker->company(),
            'industry' => 'retail',
            'content_language' => 'ar',
            'platforms' => ['instagram', 'facebook'],
            'colors' => ['#111111', '#f5f5f5'],
        ];
    }
}
