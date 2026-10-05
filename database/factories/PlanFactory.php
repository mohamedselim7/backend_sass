<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = \App\Models\Plan::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(1),
            'name' => 'Plan',
            'price' => 499,
            'currency' => 'EGP',
            'interval' => 'month',
            'monthly_credits' => 300,
            'is_active' => true,
        ];
    }
}
