<?php

namespace App\Services\Social\DTO;

/** What to publish, independent of platform. */
class PublishPayload
{
    /** @param string[] $media */
    public function __construct(
        public readonly ?string $text = null,
        public readonly array $media = [],
        public readonly ?string $link = null,
    ) {}
}
