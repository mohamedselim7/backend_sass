<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = \App\Models\Media::class;

    public function definition(): array
    {
        $path = 'media/'.$this->faker->uuid().'.jpg';

        return [
            'user_id' => User::factory(),
            'disk' => 'public',
            'path' => $path,
            'url' => '/storage/'.$path,
            'mime' => 'image/jpeg',
            'size' => 1024,
            'folder' => null,
        ];
    }
}
