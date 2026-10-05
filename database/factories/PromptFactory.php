<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromptFactory extends Factory
{
    protected $model = \App\Models\Prompt::class;

    public function definition(): array
    {
        $body = $this->faker->sentence();

        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'body' => $body,
            'prompt' => $body,
            'is_shared' => false,
            'is_favorite' => false,
        ];
    }
}
