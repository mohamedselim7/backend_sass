<?php

namespace App\Services\Ai\Contracts;

/**
 * A provider that can list the text/chat models actually available to the
 * configured API key. The provider API is the source of truth; config values
 * are only preferences that are validated against it.
 */
interface ModelDiscoveryInterface
{
    /**
     * Usable text/chat models, normalized as
     * [['id' => string, 'name' => string, 'capabilities' => ['text']], ...].
     * Uses the cache unless $forceRefresh is true.
     *
     * @return array<int, array{id:string,name:string,capabilities:array<int,string>}>
     */
    public function getAvailableModels(bool $forceRefresh = false): array;

    public function isModelAvailable(string $model): bool;

    public function getRecommendedModel(): ?string;
}
