<?php

namespace App\Services\Ai\DTO;

readonly class ImageResult
{
    public function __construct(
        public string $base64OrUrl,
        public bool $isUrl,
        public string $mime,
        public string $model,
        public string $provider,
    ) {}
}
