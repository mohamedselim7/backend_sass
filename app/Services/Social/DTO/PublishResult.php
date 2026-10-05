<?php

namespace App\Services\Social\DTO;

class PublishResult
{
    public function __construct(
        public readonly ?string $externalPostId,
        public readonly ?string $permalink = null,
    ) {}
}
