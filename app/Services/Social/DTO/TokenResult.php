<?php

namespace App\Services\Social\DTO;

/** Normalised OAuth token exchange result, regardless of provider quirks. */
class TokenResult
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly ?int $expiresIn = null,
        public readonly array $raw = [],
    ) {}
}
