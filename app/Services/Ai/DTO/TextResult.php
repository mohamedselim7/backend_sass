<?php

namespace App\Services\Ai\DTO;

readonly class TextResult
{
    public function __construct(
        public string $text,
        public ?int $promptTokens,
        public ?int $completionTokens,
        public string $model,
        public string $provider,
    ) {}
}
